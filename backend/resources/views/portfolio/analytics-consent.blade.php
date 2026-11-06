@if(app()->environment('production') && is_string($measurementId) && preg_match('/\AG-[A-Z0-9]+\z/', $measurementId) === 1)
  <section class="analytics-consent" id="analytics-consent" aria-label="Analytics cookie choices" hidden>
    <div class="analytics-consent-copy">
      <p class="eyebrow">YOUR PRIVACY</p>
      <p>Allow optional Google Analytics to measure visits and basic interactions. It stays off unless you choose to allow it. You can change this choice at any time.</p>
      <a href="/privacy">Read the privacy notice</a>
    </div>
    <div class="analytics-consent-actions">
      <button class="button button-quiet" id="analytics-reject" type="button">Reject optional</button>
      <button class="button button-primary" id="analytics-allow" type="button">Allow analytics</button>
    </div>
  </section>
  <button class="analytics-settings" id="analytics-settings" type="button" hidden>Privacy settings</button>
  <script>
    (() => {
      const measurementId = @json($measurementId);
      const preferenceName = "site_analytics_consent";
      const banner = document.getElementById("analytics-consent");
      const settings = document.getElementById("analytics-settings");

      function currentChoice() {
        const prefix = `${preferenceName}=`;
        const entry = document.cookie.split(";").map((part) => part.trim()).find((part) => part.startsWith(prefix));
        const choice = entry?.slice(prefix.length);
        return choice === "granted" || choice === "denied" ? choice : null;
      }

      function saveChoice(choice) {
        const domain = location.hostname === "hamidbayat.co.uk" || location.hostname.endsWith(".hamidbayat.co.uk")
          ? "; Domain=.hamidbayat.co.uk"
          : "";
        const secure = location.protocol === "https:" ? "; Secure" : "";
        document.cookie = `${preferenceName}=${choice}; Max-Age=15552000; Path=/; SameSite=Lax${secure}${domain}`;
      }

      function removeAnalyticsCookies() {
        for (const part of document.cookie.split(";")) {
          const name = part.split("=")[0].trim();
          if (!name.startsWith("_ga")) continue;
          document.cookie = `${name}=; Max-Age=0; Path=/; SameSite=Lax`;
          document.cookie = `${name}=; Max-Age=0; Path=/; Domain=.hamidbayat.co.uk; SameSite=Lax; Secure`;
        }
      }

      function enableAnalytics() {
        if (document.querySelector(`script[data-ga-measurement="${measurementId}"]`)) return;

        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag("consent", "default", {
          analytics_storage: "denied",
          ad_storage: "denied",
          ad_user_data: "denied",
          ad_personalization: "denied",
        });
        window.gtag("consent", "update", {
          analytics_storage: "granted",
          ad_storage: "denied",
          ad_user_data: "denied",
          ad_personalization: "denied",
        });
        window.gtag("js", new Date());
        window.gtag("config", measurementId, {
          allow_google_signals: false,
          allow_ad_personalization_signals: false,
        });

        const tag = document.createElement("script");
        tag.async = true;
        tag.dataset.gaMeasurement = measurementId;
        tag.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
        document.head.append(tag);
      }

      function closeChoices() {
        banner.hidden = true;
        settings.hidden = false;
      }

      function choose(choice) {
        const previousChoice = currentChoice();
        saveChoice(choice);
        closeChoices();

        if (choice === "granted") {
          enableAnalytics();
          return;
        }

        removeAnalyticsCookies();
        if (previousChoice === "granted") window.location.reload();
      }

      document.getElementById("analytics-allow").addEventListener("click", () => choose("granted"));
      document.getElementById("analytics-reject").addEventListener("click", () => choose("denied"));
      settings.addEventListener("click", () => {
        banner.hidden = false;
        settings.hidden = true;
      });

      const choice = currentChoice();
      if (choice === "granted") {
        settings.hidden = false;
        enableAnalytics();
      } else if (choice === "denied") {
        settings.hidden = false;
      } else {
        banner.hidden = false;
      }
    })();
  </script>
@endif
