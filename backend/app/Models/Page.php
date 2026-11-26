<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    /**
     * Addresses the site already uses, so a page can never hide them.
     * Pages live at the top level: /{slug}.
     */
    public const RESERVED_SLUGS = [
        'admin', 'api', 'app', 'contact', 'favicon', 'fonts', 'home', 'index', 'login', 'logout', 'media',
        'pages', 'preview', 'privacy', 'projects', 'robots', 'sitemap', 'storage', 'styles', 'testimonials', 'up',
        'portfolio-data', 'build', 'vendor', 'assets', 'public',
    ];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    protected $fillable = [
        'title', 'slug', 'summary', 'body', 'status', 'publish_at', 'seo_title', 'seo_description',
        'media_asset_id', 'noindex', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'noindex' => 'boolean', 'media_asset_id' => 'integer'];
    }

    /** Visible to visitors: published, and the publish time (if any) has passed. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    public function isLive(): bool
    {
        return ! $this->trashed() && $this->status === 'published' && (! $this->publish_at || $this->publish_at->lte(now()));
    }

    public function isScheduled(): bool
    {
        return $this->status === 'published' && $this->publish_at && $this->publish_at->isFuture();
    }

    public function stateLabel(): string
    {
        return match (true) {
            $this->trashed() => 'In the trash',
            $this->isScheduled() => 'Scheduled for '.$this->publish_at->format('j M Y, H:i').' UTC',
            $this->status === 'published' => 'Published',
            default => 'Draft',
        };
    }

    public function url(): string
    {
        return url('/'.$this->slug);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
