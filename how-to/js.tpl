/*
// Example Example based on https://schema.org/HowTo
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "How to Change a Car Tire",
  "description": "A step-by-step guide to changing a flat car tire safely and quickly.",
  "image": "https://example.com/images/change-tire.jpg",
  "prepTime": "PT15M",
  "performTime": "PT45M",
  "totalTime": "PT1H",
  "video": {
    "@type": "VideoObject",
    "name": "How to Change a Flat Tire",
    "description": "Watch this video to learn how to change a flat tire quickly and safely.",
    "thumbnailUrl": "https://example.com/thumbnails/tire-video.jpg",
    "uploadDate": "2024-06-01",
    "contentUrl": "https://example.com/videos/change-tire.mp4",
    "embedUrl": "https://www.youtube.com/embed/abc123xyz",
    "duration": "PT5M30S"
  },
  "estimatedCost": {
    "@type": "MonetaryAmount",
    "currency": "USD",
    "value": "10"
  },
  "supply": [
    {
      "@type": "HowToSupply",
      "name": "Spare tire"
    },
    {
      "@type": "HowToSupply",
      "name": "Car manual"
    }
  ],
  "tool": [
    {
      "@type": "HowToTool",
      "name": "Jack"
    },
    {
      "@type": "HowToTool",
      "name": "Lug wrench"
    }
  ],
  "performer": {
    "@type": "Person",
    "name": "John Doe"
  },
  "step": [
    {
      "@type": "HowToStep",
      "url": "https://example.com/change-tire#step1",
      "name": "Park the vehicle safely",
      "image": "https://example.com/images/step1.jpg",
      "text": "Pull over to a flat, safe location and turn on your hazard lights."
    },
    {
      "@type": "HowToStep",
      "url": "https://example.com/change-tire#step2",
      "name": "Loosen the lug nuts",
      "image": "https://example.com/images/step2.jpg",
      "text": "Use the wrench to loosen each lug nut about a quarter of a turn."
    },
    {
      "@type": "HowToStep",
      "name": "Raise the vehicle with a jack",
      "image": "https://example.com/images/step3.jpg",
      "text": "Place the jack under the vehicle's frame and lift it until the tire is off the ground."
    },
    {
      "@type": "HowToStep",
      "name": "Remove the flat tire and replace with spare",
      "image": "https://example.com/images/step4.jpg",
      "text": "Fully remove the loosened lug nuts, remove the flat tire, and install the spare."
    },
    {
      "@type": "HowToStep",
      "name": "Lower the car and tighten the lug nuts",
      "image": "https://example.com/images/step5.jpg",
      "text": "Lower the car and then fully tighten the lug nuts in a star pattern."
    }
  ]
}
*/

/* ============================================================================
   How-To widget — client-side pagination
   ----------------------------------------------------------------------------
   Splits the rendered instruction steps / posts into pages that switch
   without reloading the page. Two modes:
     - "numbers"   : numbered pages with prev / next (compact range + ellipsis)
     - "load_more" : a single button that reveals the next chunk

   Everything is scoped to a single widget instance and driven from the
   data-* attributes on the widget root, so it is safe to use several widgets
   on one page and inside tabs (switching tabs only toggles display and never
   resets the pagination state or triggers a reload).

   Fail-open: if this script never runs, every step stays visible.
   ============================================================================ */
(function () {
  "use strict";

  var SELECTOR = ".ue-howto-widget[data-howto-pagination='1']";

  function toInt(value, fallback) {
    var n = parseInt(value, 10);
    return (isNaN(n) || n < 1) ? fallback : n;
  }

  // Direct step wrappers inside the instructions block. Each item is either
  // a `.ue-step` (plain) or an `.ue-step-link` <a> wrapping a `.ue-step`
  // (Posts / WooCommerce Products source). The heading and the pagination
  // nav are intentionally excluded.
  function getItems(instructions) {
    var items = [];
    var children = instructions.children;
    for (var i = 0; i < children.length; i++) {
      var el = children[i];
      if (el.classList.contains("ue-step") || el.classList.contains("ue-step-link")) {
        items.push(el);
      }
    }
    return items;
  }

  function stepOf(item) {
    return item.classList.contains("ue-step") ? item : item.querySelector(".ue-step");
  }

  function makeButton(label, cls) {
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "ue-howto-btn " + cls;
    btn.innerHTML = label;
    return btn;
  }

  // Compact page list, e.g. [1, "...", 4, 5, 6, "...", 20]
  function pageRange(current, total) {
    var delta = 1, range = [], out = [], last = 0;
    for (var i = 1; i <= total; i++) {
      if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
        range.push(i);
      }
    }
    for (var j = 0; j < range.length; j++) {
      var page = range[j];
      if (last) {
        if (page - last === 2) out.push(last + 1);
        else if (page - last > 2) out.push("...");
      }
      out.push(page);
      last = page;
    }
    return out;
  }

  function init(root) {
    if (root.getAttribute("data-howto-initialized") === "1") return;

    var instructions = root.querySelector(".ue-instructions");
    if (!instructions) return;

    var items = getItems(instructions);
    var perPage = toInt(root.getAttribute("data-howto-perpage"), items.length || 1);

    // Nothing to paginate — leave every step visible.
    if (items.length <= perPage) {
      root.setAttribute("data-howto-initialized", "1");
      return;
    }
    root.setAttribute("data-howto-initialized", "1");

    var mode = root.getAttribute("data-howto-mode") || "numbers";
    var scrollTop = root.getAttribute("data-howto-scrolltop") === "true";
    var totalPages = Math.ceil(items.length / perPage);

    var nav = document.createElement(mode === "numbers" ? "nav" : "div");
    nav.className = "ue-howto-pagination ue-howto-pagination--" + mode;
    nav.setAttribute("role", "navigation");
    nav.setAttribute("aria-label", "Pagination");
    instructions.appendChild(nav);

    // Show items in the half-open range [from, to); hide the rest. Also moves
    // the "hide dangling timeline connector" marker to the last visible step.
    function setVisible(from, to) {
      for (var i = 0; i < items.length; i++) {
        items[i].style.display = (i >= from && i < to) ? "" : "none";
        var s = stepOf(items[i]);
        if (s) s.classList.remove("ue-howto-hide-line");
      }
      var lastStep = stepOf(items[to - 1]);
      if (lastStep) lastStep.classList.add("ue-howto-hide-line");
    }

    function maybeScroll() {
      if (!scrollTop) return;
      var top = instructions.getBoundingClientRect().top;
      if (top < 0) {
        window.scrollTo({ top: window.pageYOffset + top - 20, behavior: "smooth" });
      }
    }

    // ---- Load more --------------------------------------------------------
    if (mode === "load_more") {
      var shown = perPage;
      setVisible(0, shown);

      var moreBtn = makeButton(root.getAttribute("data-howto-loadmore") || "Load More", "ue-howto-loadmore");
      nav.appendChild(moreBtn);

      moreBtn.addEventListener("click", function (ev) {
        ev.preventDefault();
        shown = Math.min(shown + perPage, items.length);
        setVisible(0, shown);
        if (shown >= items.length) nav.parentNode.removeChild(nav);
      });
      return;
    }

    // ---- Numbered pages ---------------------------------------------------
    var current = 1;

    function goTo(page) {
      if (page < 1 || page > totalPages || page === current) return;
      current = page;
      render();
      maybeScroll();
    }

    function render() {
      var start = (current - 1) * perPage;
      setVisible(start, Math.min(start + perPage, items.length));

      nav.innerHTML = "";

      var prev = makeButton(root.getAttribute("data-howto-prev") || "Prev", "ue-howto-prev");
      if (current === 1) prev.setAttribute("disabled", "disabled");
      prev.addEventListener("click", function (ev) { ev.preventDefault(); goTo(current - 1); });
      nav.appendChild(prev);

      pageRange(current, totalPages).forEach(function (p) {
        if (p === "...") {
          var gap = document.createElement("span");
          gap.className = "ue-howto-ellipsis";
          gap.textContent = "…";
          nav.appendChild(gap);
          return;
        }
        var btn = makeButton(String(p), "ue-howto-page-num");
        if (p === current) {
          btn.classList.add("ue-howto-active");
          btn.setAttribute("aria-current", "page");
        }
        btn.addEventListener("click", function (ev) { ev.preventDefault(); goTo(p); });
        nav.appendChild(btn);
      });

      var next = makeButton(root.getAttribute("data-howto-next") || "Next", "ue-howto-next");
      if (current === totalPages) next.setAttribute("disabled", "disabled");
      next.addEventListener("click", function (ev) { ev.preventDefault(); goTo(current + 1); });
      nav.appendChild(next);
    }

    render();
  }

  function boot() {
    var widgets = document.querySelectorAll(SELECTOR);
    for (var i = 0; i < widgets.length; i++) init(widgets[i]);
  }

  // Run immediately for widgets already parsed (this script is emitted right
  // after the widget markup) to minimise any flash, then again on DOM ready
  // to catch the rest. init() is idempotent, so running twice is harmless.
  boot();
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  }
})();