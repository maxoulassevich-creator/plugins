{% if item.link is not empty %}
<a href="{{item.link}}" class="ue-step-link" {{item.link_html_attributes|ucsafe|raw}}>
{% endif %}
<div class="ue-step ue-step-{{item.item_index}}{% if item.link is not empty %} ue-step-clickable{% endif %}">
  <div class="ue-step-progress">
    <div class="ue-step-indicator">
      {% if step_indicator_type == "index" %}{{item.item_index}}{% elseif step_indicator_type == "icon" %}{{item.icon_html|ucsafe|raw}}{% endif %}
    </div>
    {% if instruction_style == "timeline" %}
      <div class="ue-progress-line" aria-hidden="true"></div>
    {% endif %}
  </div>
  <div class="ue-instructions-content">
    <div class="ue-step-title-desc">
      <h3 class="ue-step-title">{{item.title|ucsafe|raw}}</h3>
      <p class="ue-step-desc">{{item.description|ucsafe|raw}}</p>
    </div>
    <img href="{{item.image}}" {{item.image_attributes|ucsafe|raw}} alt="{% if image_alt is not empty %}{{item.image_alt}}{% else %}{{item.image_caption}}{% endif %}" class="ue-step-img" >
  </div>
</div>
{% if item.link is not empty %}
</a>
{% endif %}
