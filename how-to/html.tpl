<div id="{{uc_id}}" class="ue-howto-widget">
  
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