{{ link_to("module-http-alert/module-http-alert/modify", '<i class="gear icon"></i>  '~t._('module_httpAlert_ChangeRecord'), "class": "ui blue button", "id":"change-record") }}
{{ link_to("module-http-alert/module-http-alert/modifyDid", '<i class="gear icon"></i>  '~t._('module_httpAlert_AddDidRule'), "class": "ui blue button", "id":"change-did") }}



{% for record in didUrl %}
    {% if loop.first %}
        <table class="ui selectable compact table" id="users-groups-table">
        <thead>
        <tr>
            <th>{{ t._('module_httpAlert_did_shot') }}</th>
            <th class="center aligned">{{ t._('module_httpAlert_url') }}</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
    {% endif %}
    <tr class="group-row" id="{{ record.id }}">
        <td>{{ record.did }}</td>
        <td class="center aligned">{{ record.url}}</td>
        {{ partial("partials/tablesbuttons",
            [
                'id': record.id,
                'edit' : 'module-http-alert/module-http-alert/modifyDid/',
                'delete': 'module-http-alert/module-http-alert/deleteDid/'
            ]) }}
    </tr>

    {% if loop.last %}

        </tbody>
        </table>
    {% endif %}
{% endfor %}