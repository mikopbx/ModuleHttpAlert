<form class="ui large form" id="module-did-url-form">
    {{ form.render('id') }}
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_did') }}</label>
        {{ form.render('did') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_url') }}</label>
        {{ form.render('url') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_uWwwasic') }}</label>
        {{ form.render('uWwwBasic') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_pWwwBasic') }}</label>
        {{ form.render('pWwwBasic')}}
    </div>
    {{ partial("partials/submitbutton",['indexurl':'module-http-alert/module-http-alert/index']) }}
</form>