document.addEventListener("DOMContentLoaded", () => {
  const headerTarget = document.querySelector("[data-site-part='header']");
  const footerTarget = document.querySelector("[data-site-part='footer']");

  const baseUrl = new URL(window.location.href);
  const localHeader = new URL("_shared/header.html", baseUrl);
  const localFooter = new URL("_shared/footer.html", baseUrl);
  const sharedHeader = new URL("/shared/header.html", baseUrl);
  const sharedFooter = new URL("/shared/footer.html", baseUrl);

  const loadPart = (target, primaryUrl, fallbackUrl) => {
    if (!target) {
      return Promise.resolve(false);
    }

    return fetch(primaryUrl)
      .then((res) => (res.ok ? res.text() : ""))
      .then((html) => {
        if (html) {
          target.innerHTML = html;
          return true;
        }

        return fetch(fallbackUrl)
          .then((fallbackRes) => (fallbackRes.ok ? fallbackRes.text() : ""))
          .then((fallbackHtml) => {
            target.innerHTML = fallbackHtml || "";
            return Boolean(fallbackHtml);
          })
          .catch(() => false);
      })
      .catch(() => false);
  };

  Promise.all([
    loadPart(headerTarget, localHeader, sharedHeader),
    loadPart(footerTarget, localFooter, sharedFooter),
  ]).then(() => {
    if (typeof window.onSitePartsLoaded === "function") {
      window.onSitePartsLoaded();
    }
  });
});
