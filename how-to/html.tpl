<div id="{{uc_id}}" class="ue-howto-widget"
  {% if show_instructions == "true" and enable_pagination == "true" %}
    data-howto-pagination="1"
    data-howto-perpage="{{pagination_items_per_page}}"
    data-howto-mode="{{pagination_mode}}"
    data-howto-scrolltop="{{pagination_scroll_to_top}}"
    data-howto-prev="{{pagination_prev_text}}"
    data-howto-next="{{pagination_next_text}}"
    data-howto-loadmore="{{pagination_load_more_text}}"
  {% endif %}
>
  
    <div class="ue-howto-content">
        {% if show_image == "true" %}
          <div class="ue-howto-image-wrapper">
            <img class="ue-howto-image" src="{{howto_image}}" alt="{{howto_image_alt}}" title="{{howto_image_title}}" role="img" aria-label="howto image"/>
          </div>
        {% endif %}
        <div class="ue-title-desc-wrapper">
          {% if show_title == "true" %}<{{title_tag}} {% if title_tag == "a" %}href="{{title_link}}" {{title_link_html_attributes|ucsafe|raw}}{% endif %} class="ue-howto-title">{{title|ucsafe|raw}}</{{title_tag}}>{% endif %}
          {% if show_description == "true" %}
            <p class="ue-description">
              {{description|ucsafe|raw}}
            </p>
          {% endif %}
        </div>
    </div>   

      {% if show_statistics == "true" %}
        {% if show_statistic_divider == "true" %}<div class="ue-stats-divider"></div>{% endif %}
        <div class="ue-stats">
          {% if statistic_1_title is not empty %}
            <div class="ue-stat">
              <span class="ue-stat-icon" aria-hidden="true">{{statistic_1_icon_html|ucsafe|raw}}</span>
              <span class="ue-stat-title">{{statistic_1_title|ucsafe|raw}}</span>
              <span class="ue-stat-text">{{statistic_1_text|ucsafe|raw}}</span>
            </div>
          {% endif %}
          {% if statistic_2_title is not empty %}
            <div class="ue-stat">
              <span class="ue-stat-icon" aria-hidden="true">{{statistic_2_icon_html|ucsafe|raw}}</span>
              <span class="ue-stat-title">{{statistic_2_title|ucsafe|raw}}</span>
              <span class="ue-stat-text">{{statistic_2_text|ucsafe|raw}}</span>
            </div>
          {% endif %}
          {% if statistic_3_title is not empty %}
            <div class="ue-stat">
              <span class="ue-stat-icon" aria-hidden="true">{{statistic_3_icon_html|ucsafe|raw}}</span>
              <span class="ue-stat-title">{{statistic_3_title|ucsafe|raw}}</span>
              <span class="ue-stat-text">{{statistic_3_text|ucsafe|raw}}</span>
            </div>
          {% endif %}
          {% if statistic_4_title is not empty %}
            <div class="ue-stat">
              <span class="ue-stat-icon">{{statistic_4_icon_html|ucsafe|raw}}</span>
              <span class="ue-stat-title">{{statistic_4_title|ucsafe|raw}}</span>
              <span class="ue-stat-text">{{statistic_4_text|ucsafe|raw}}</span>
            </div>
          {% endif %}
        </div>
      {% endif %}

    {% if show_supply == "true" %}
      {% set supply_list = supplies|split(',') %}
      {% if show_supply_divider == "true" %}<div class="ue-supplies-divider"></div>{% endif %}
      <div class="ue-supplies">
        <h2 class="ue-heading">{{supply_title|ucsafe|raw}}</h2>
        <ul class="ue-supply-ul">
          {% for supply in supply_list %}
             {% set supply = supply|trim %}
             {% if supply is not empty %}
               <li class="ue-supply">
                 {% if supply_list_marker == "icon" %}{{list_marker_icon_html|ucsafe|raw}}{% endif %}
                 {{ supply }}
               </li>
             {% endif %}
          {% endfor %}
        </ul>
      </div>
    {% endif %}

    {% if show_tools == "true" %}
      {% set tools_list = tools|split(',') %}
      {% if show_tools_divider == "true" %}<div class="ue-tools-divider"></div>{% endif %}
      <div class="ue-tools">
        <h2 class="ue-heading">{{tools_title|ucsafe|raw}}</h2>
        <ul class="ue-tool-ul">
          {% for tool in tools_list %}
             {% set tool = tool|trim %}
             {% if tool is not empty %}
               <li class="ue-tool">
                 {% if tools_list_marker_type == "icon" %}{{tools_list_marker_icon_html|ucsafe|raw}}{% endif %}
                 {{ tool }}
               </li>
             {% endif %}
          {% endfor %}
        </ul>
      </div>
    {% endif %}

    {% set buttons %}
      {% if show_button == "true" %}
        {% if show_button_divider == "true" %}<div class="ue-btn-divider"></div>{% endif %}
        <div class ="ue-button-wrapper">
          <a class="ue-button" href="{{button_link}}" {{button_link_html_attributes|ucsafe|raw}}>
            {{button_text|ucsafe|raw}}
            {% if show_button_icon == "true" %}{{button_icon_html|ucsafe|raw}}{% endif %}
          </a>
          {% if show_button_2 == "true" %}
            <a class="ue-button-2" href="{{button_2_link}}" {{button_2_link_html_attributes|ucsafe|raw}}>
              {{button_2_text|ucsafe|raw}}
              {% if show_button_2_icon == "true" %}{{button_2_icon_html|ucsafe|raw}}{% endif %}
            </a>
          {% endif %}
        </div>
      {% endif %}
    {% endset %}

    {% if button_position == "above_instruction" %}{{buttons}}{% endif %}
  
    {% if show_instructions == "true" %}
      {% if show_instructions_divider == "true" %}<div class="ue-instructions-divider"></div>{% endif %}
      <div class="ue-instructions">
        <h2 class="ue-heading">{{instructions_text|ucsafe|raw}}</h2>
        {{put_items()}}
      </div>
    {% endif %}
  
    {% if show_video == "true" %}
      {% if show_video_divider == "true" %}<div class="ue-video-divider"></div>{% endif %}
      <div class="ue-video-section">
        <h2 class="ue-heading">{{video_text|ucsafe|raw}}</h2>
        <div class="ue-video-wrapper">
          {% if video_source == "self-hosted" %}
            <video controls>
              <source src="{{self_hosted_video_link}}" controls type="video/mp4">
               Your browser does not support this video player.
            </video>
          {% elseif video_source == "youtube" %}
            <iframe src="{{youtube_video_link}}"
              title="YouTube video player"
              frameborder="0"
              allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
              referrerpolicy="strict-origin-when-cross-origin"
              allowfullscreen>
            </iframe>
          {% endif %}
        </div>
      </div>
    {% endif %}
      
    {% if show_end_note == "true" %}<p class="ue-howto-note">{{end_note|ucsafe|raw}}</p>{% endif %}
    
    {% if button_position == "bottom" %}{{buttons}}{% endif %}

    {% if show_badge == "true" %}
      <div class="ue-badge-wrapper">
        <div class="ue-badge">
          <span class="ue-badge-icon">{{badge_icon_html|ucsafe|raw}}</span>
          {{badge_text|ucsafe|raw}}
        </div>
      </div>
    {% endif %}

</div>

{% if show_instructions == "true" and enable_pagination == "true" %}
<script>
/* ============================================================================
   How-To widget - client-side pagination.

   Deliberately shipped inline with the markup rather than through the addon
   JS asset: on logged-out page views caching / optimisation plugins inline
   and reorder the bundles, so addon JS can run before jQuery exists and die
   silently - which is why pagination worked for logged-in admins only. This
   script needs neither jQuery nor the bundle, so it survives that treatment.

   The implementation is defined once per page; each widget instance below
   only calls boot(). Fail-open: if it never runs, every step stays visible.
   ============================================================================ */
(function () {
  "use strict";

  if (!window.ueHowToPagination) {
    window.ueHowToPagination = (function () {

      var SELECTOR = ".ue-howto-widget[data-howto-pagination='1']";

      function toInt(value, fallback) {
        var n = parseInt(value, 10);
        return (isNaN(n) || n < 1) ? fallback : n;
      }

      // Direct step wrappers inside the instructions block: either a `.ue-step`
      // or an `.ue-step-link` <a> wrapping one (Posts / WooCommerce sources).
      // The heading and the pagination nav are intentionally excluded.
      function getItems(instructions) {
        var items = [], children = instructions.children;
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

        root.setAttribute("data-howto-initialized", "1");

        // Nothing to paginate - leave every step visible.
        if (items.length <= perPage) return;

        var mode = root.getAttribute("data-howto-mode") || "numbers";
        var scrollTop = root.getAttribute("data-howto-scrolltop") === "true";
        var totalPages = Math.ceil(items.length / perPage);

        var nav = document.createElement(mode === "numbers" ? "nav" : "div");
        nav.className = "ue-howto-pagination ue-howto-pagination--" + mode;
        nav.setAttribute("role", "navigation");
        nav.setAttribute("aria-label", "Pagination");
        instructions.appendChild(nav);

        // Show items in the half-open range [from, to); hide the rest. Also
        // moves the "hide dangling connector" marker to the last visible step.
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

        // ---- Load more ----
        if (mode === "load_more") {
          var shown = perPage;
          setVisible(0, shown);

          var moreBtn = makeButton(root.getAttribute("data-howto-loadmore") || "Load More", "ue-howto-loadmore");
          nav.appendChild(moreBtn);

          moreBtn.addEventListener("click", function (ev) {
            ev.preventDefault();
            shown = Math.min(shown + perPage, items.length);
            setVisible(0, shown);
            if (shown >= items.length && nav.parentNode) nav.parentNode.removeChild(nav);
          });
          return;
        }

        // ---- Numbered pages ----
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
              gap.textContent = "\u2026";
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

      function isPaginated(el) {
        return el.nodeType === 1 && el.classList &&
          el.classList.contains("ue-howto-widget") &&
          el.getAttribute("data-howto-pagination") === "1";
      }

      // Initialise the node itself (when Elementor hands us a widget scope)
      // plus any paginated widget nested inside it.
      function initWithin(node) {
        if (!node || node.nodeType !== 1) return;
        if (isPaginated(node)) init(node);
        if (!node.querySelectorAll) return;
        var found = node.querySelectorAll(SELECTOR);
        for (var i = 0; i < found.length; i++) init(found[i]);
      }

      function boot() {
        initWithin(document.body || document.documentElement);
      }

      // Elementor renders widgets through its own lifecycle (popups, lazy
      // tabs, the editor preview), so hook that as well. jQuery is optional
      // here and only ever touched once it is a real callable function.
      var elementorHooked = false;
      function hookElementor() {
        if (elementorHooked) return;
        if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
        elementorHooked = true;
        window.elementorFrontend.hooks.addAction("frontend/element_ready/global", function (scope) {
          initWithin(scope && scope[0] ? scope[0] : scope);
        });
      }

      // Last-resort net: catches markup injected by AJAX tab / filter scripts
      // and by optimisation plugins that rebuild the DOM after load. init()
      // is idempotent, so the extra passes are no-ops.
      var watching = false;
      function watch() {
        if (watching || !window.MutationObserver || !document.body) return;
        watching = true;
        var timer = null;
        new MutationObserver(function () {
          if (timer) clearTimeout(timer);
          timer = setTimeout(boot, 100);
        }).observe(document.body, { childList: true, subtree: true });
      }

      function ready() {
        boot();
        watch();
        hookElementor();
      }

      // Initialise from every available entry point, so the widget still comes
      // up when the script is deferred, delayed, inlined or reordered.
      if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", ready);
      } else {
        ready();
      }
      window.addEventListener("load", ready);

      if (typeof window.jQuery === "function") {
        window.jQuery(window).on("elementor/frontend/init", hookElementor);
      }

      return { boot: boot, init: init, initWithin: initWithin };
    })();
  }

  window.ueHowToPagination.boot();
})();
</script>
{% endif %}