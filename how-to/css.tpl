#{{uc_id}} *{
  box-sizing: border-box;
}
#{{uc_id}}.ue-howto-widget {
  overflow: hidden;
  max-width:100%;
  width:100%;
  direction:{{rtl}}!important;
  position:relative;
}

#{{uc_id}} .ue-howto-image-wrapper {
  position: relative;
  max-width:100%;
  overflow: hidden;
  flex-shrink:0;
}

#{{uc_id}} .ue-howto-image {
  width: 100%;
  display: block;
}
#{{uc_id}} .ue-title-desc-wrapper{
  flex-grow:1;
}
#{{uc_id}} .ue-howto-content{
  display:flex;
}
#{{uc_id}} .ue-heading {
  margin-block-start: 0;
}
{% if show_title == "true" %}
  #{{uc_id}} .ue-howto-title {
      margin-top: 0;
  }
{% endif %}

{% if show_description == "true" %}
  #{{uc_id}} .ue-description {
    margin-block-end: 0;
  }
{% endif %}

{% if show_statistics == "true" %}
#{{uc_id}} .ue-stats {
  display: grid;
}
#{{uc_id}} .ue-stat {
  display: flex;
  flex-direction:column;
}
{% endif %}

  #{{uc_id}} .ue-step {
    display: flex;
    flex-direction:row;
  }
  #{{uc_id}} .ue-step:last-child .ue-progress-line {
    display: none !important;
  }
  #{{uc_id}} .ue-step-progress{
    display:flex;
    flex-direction:column;
    align-items:center;
    position:relative;
  }
  #{{uc_id}} .ue-step-indicator{
    display:flex;
    justify-content:center;
    align-items:center;
    text-align: center;
  }
  #{{uc_id}} .ue-instructions-content {
    display:flex;
    flex: 1;
    flex-shrink: 0;
  }
  {% if instruction_style == "timeline" %}
    #{{uc_id}} .ue-progress-line{
      flex-grow:1;
    } 
  {% endif %}
  #{{uc_id}} .ue-step-title {
    margin-block-start: 0;
    margin-block-end: 0;
  }
  #{{uc_id}} .ue-step-title-desc{
    flex-grow: 1;
  }
  #{{uc_id}} .ue-step-img{
    {% if instructuion_image_height_type == "aspect" %}
      height: fit-content;
    {% elseif instructuion_image_height_type == "auto"  %}
      height: auto;
    {% endif %}
  }

#{{uc_id}} .ue-video-wrapper {
  overflow:hidden;
  display: flex;
  justify-content: center;
  align-items: center;
  width:100%;
  position:relative;
}
#{{uc_id}} .ue-video-wrapper iframe {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

#{{uc_id}} svg{
  width:1em;
  height:1em;
}

{% if show_supply == "true" %}
  #{{uc_id}} .ue-supply-ul {
    list-style: none;
    padding-left: 0;
    display:flex;
    flex-wrap: wrap;
  }
  #{{uc_id}} .ue-supply {
    position: relative;
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }
  {% if supply_list_marker == "custom" or supply_list_marker == "text_symbol" %}
  #{{uc_id}} .ue-supply::before {
    content: '{% if supply_list_marker == "text_symbol" %}{{list_marker_text_symbol|ucsafe|raw}}{% endif %}';
  }
  {% endif %}
{% endif %}

{% if show_tools == "true" %}
  #{{uc_id}} .ue-tool-ul {
    list-style: none;
    padding-left: 0;
    display:flex;
    flex-wrap: wrap;
  }
  #{{uc_id}} .ue-tool {
    position: relative;
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }
  {% if tools_list_marker_type == "custom" or tools_list_marker_type == "text_symbol" %}
  #{{uc_id}} .ue-tool::before {
    content: '{% if tools_list_marker_type == "text_symbol" %}{{tools_list_marker_text_symbol|ucsafe|raw}}{% endif %}';
  }
  {% endif %}
{% endif %}

{% if show_end_note == "true" %}
  #{{uc_id}} .ue-howto-note{
    margin-block-end: 0;
  }
{% endif %}

{% if show_badge == "true" %}
  #{{uc_id}} .ue-badge-wrapper{
    transform: rotate(90deg);
    position:absolute;
    top:0;
    right:0;
    bottom:unset;
    left:unset;
  }
  #{{uc_id}} .ue-badge{
    width: 150%;
    display:inline-flex;
    justify-content:center;
    align-items:center;
  }
  #{{uc_id}} .ue-badge-icon{
    display:inline-flex;
    justify-content:center;
    align-items:center;
  }
{% endif %}
.ue-heading {
  font-size:25px;
  font-weight:600;
}

{% if show_button == "true" %}
  #{{uc_id}} .ue-button-wrapper{
    display:flex;
    flex-direction:row;
  }
  #{{uc_id}} .ue-button{
    display:inline-flex;
    justify-content:center;
    align-items:center;
  }
  {% if show_button_2 == "true" %}
    #{{uc_id}} .ue-button-2{
      display:inline-flex;
      justify-content:center;
      align-items:center;
    }
  {% endif %}
{% endif %}

/* Clickable card styles (Posts / WooCommerce Products) */
#{{uc_id}} a.ue-step-link {
  text-decoration: none;
  color: inherit;
  display: block;
}
#{{uc_id}} a.ue-step-link:hover,
#{{uc_id}} a.ue-step-link:focus,
#{{uc_id}} a.ue-step-link:active {
  text-decoration: none;
  color: inherit;
}
#{{uc_id}} .ue-step-clickable {
  cursor: pointer;
  transition: opacity 0.2s ease, box-shadow 0.2s ease;
}
#{{uc_id}} a.ue-step-link:hover .ue-step-clickable {
  opacity: 0.85;
}