#!/usr/bin/env bash
set -euo pipefail
umask 077
base=/home/u504051894/domains/hamidbayat.co.uk
request=$1
tag=$2
expected=$3
installed=$4
[[ "$request" =~ ^[a-f0-9-]{36}$ && "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ && "$expected" =~ ^[a-f0-9]{64}$ ]]
stage="$base/shared/update-$request"
release="$base/releases/$tag-$request"
backup="$base/backups/update-$request"
activated=0
previous=
rollback() {
  ln -s "$previous" "$base/update-rollback-$request"
  mv -Tf "$base/update-rollback-$request" "$base/public_html"
  activated=0
  echo "Update failed; previous release restored. Database was not changed."
}
cleanup() {
  status=$?
  if [[ "$status" != 0 && "$activated" == 1 ]]; then
    rollback
  fi
  if [[ -f "$stage/backup-client.cnf" ]]; then
    rm -f -- "$stage/backup-client.cnf"
  fi
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP
exec 9>"$base/shared/update.lock"
flock -n 9 || { echo "Another update is running"; exit 1; }
previous=$(readlink -f "$base/public_html")
[[ "$previous" == "$base/releases/"*"/backend/public" ]]
current=${previous%/backend/public}
[[ "$(tr -d '\r\n' < "$current/VERSION")" == "$installed" ]]
test ! -e "$release"
test ! -e "$backup"
cd "$stage"
echo "$expected  runtime.tar.gz" | sha256sum -c -
php "$stage/hosting-update.php" "$stage"
mkdir -m 700 "$backup"
mysqldump --defaults-extra-file="$stage/backup-client.cnf" --single-transaction --quick --skip-lock-tables --no-tablespaces "$(cat "$stage/database-name")" > "$backup/database.sql"
test -s "$backup/database.sql"
tar -czf "$backup/uploads.tar.gz" -C "$base/shared/storage" app
cp -p "$current/backend/.env" "$backup/environment.private"
tar -czf "$backup/source.tar.gz" -C "$current" --exclude=backend/vendor --exclude=backend/storage .
sha256sum "$backup/database.sql" "$backup/uploads.tar.gz" "$backup/environment.private" "$backup/source.tar.gz" > "$backup/SHA256SUMS"
sha256sum -c "$backup/SHA256SUMS"
mkdir -m 755 "$release"
tar -xzf "$stage/runtime.tar.gz" -C "$release"
chmod 755 "$release" "$release/backend"
find "$release/backend/public" -type d -exec chmod 755 {} +
find "$release/backend/public" -type f -exec chmod 644 {} +
cp -p "$current/backend/.env" "$release/backend/.env"
chmod 600 "$release/backend/.env"
ln -s "$base/shared/storage" "$release/backend/storage"
mkdir -p "$release/backend/bootstrap/cache"
cd "$release/backend"
php artisan config:cache
php artisan view:cache
ln -s "$base/shared/storage/app/public" "$release/backend/public/storage"
ln -s "$release/backend/public" "$base/update-next-$request"
activated=1
mv -Tf "$base/update-next-$request" "$base/public_html"
if ! curl --fail --silent --show-error --max-time 20 -D "$stage/health.headers" https://hamidbayat.co.uk/up > "$stage/health.json" \
    || ! curl --fail --silent --show-error --max-time 20 https://hamidbayat.co.uk/ > "$stage/home.html" \
    || ! curl --fail --silent --show-error --max-time 20 https://hamidbayat.co.uk/privacy > "$stage/privacy.html"; then
  rollback
  exit 1
fi
if ! tr -d '\r' < "$stage/health.headers" | grep -Fxiq "X-Portfolio-Version: ${tag#v}"; then
  rollback
  exit 1
fi
[[ "$(readlink -f "$base/public_html")" == "$release/backend/public" ]] || { rollback; exit 1; }
cp "$stage/portfolio-manifest.json" "$backup/portfolio-manifest.json"
cd "$stage"
rm -- backup-client.cnf database-name health.json health.headers home.html privacy.html runtime.tar.gz portfolio-manifest.json hosting-update.php hosting-update.sh
rmdir "$stage"
activated=0
echo "Verified release activated and public health checks passed. Backups and previous release retained."
