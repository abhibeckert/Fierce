<h1>{{ noun }} List</h1>

<div class="buttons">
  <a href="{{ controller.url('add') }}">Add {{ noun }}</a>
</div>

<table class="grid" id="{{ noun|lower }}_list">
  <thead>
    <tr>
      {% for field, name in listFields %}
        <th class="{{ field }}">{{ name }}</span>
      {% endfor %}
      <th class="buttons">&nbsp;</span>
    </tr>
  </thead>
  <tbody>
    {% for item in items %}
      <tr>
        {% for field, name in listFields %}
          <td class="{{ field }}">{% if field ends with '_html' %} {{ attribute(item, field)|raw }} {% else %} {{ attribute(item, field) }} {% endif %}</td>
        {% endfor %}
        
        <td class="buttons">
          {% if item.url %}
            <a href="{{ item.url|trim('/') }}">View</a>
          {% endif %}
          <a href="{{ controller.url('edit', {'id': item.id}) }}">Edit</a>
          <a href="javascript:void(0)" confirmedHref="{{ controller.url('delete', {'id': item.id}) }} ?>" onclick="
            if (confirm('Are you sure you want to delete {{ attribute(item, displayField)|e('js') }}?')) {
              this.setAttribute('href', this.getAttribute('confirmedHref'))
            }
          ">Delete </a>
        </td>
      </tr>
    {% endfor %}
  </tbody>
</table>

