
<form class="ui large form" id="module-http-alert-form">
    {{ form.render('id') }}

    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_urlStartCall') }}</label>
        {{ form.render('urlStartCall') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_urlDial') }}</label>
        {{ form.render('urlDial') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_urlDialEnd') }}</label>
        {{ form.render('urlDialEnd') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_urlCreateChan') }}</label>
        {{ form.render('urlCreateChan') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_urlAnswer') }}</label>
        {{ form.render('urlAnswer') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_urlHangup') }}</label>
        {{ form.render('urlHangup') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_urlCompleteCdr') }}</label>
        {{ form.render('urlCompleteCdr') }}
    </div>
    <div class="ten wide field disability">
        <label >{{ t._('module_httpAlert_urlCompleteCdrGlobal') }}</label>
        {{ form.render('urlCompleteCdrGlobal') }}
    </div>

    <div class="ui toggle checkbox">
        {{ form.render('incomingOnly') }}
        <label>{{ t._('module_httpAlert_incomingOnly') }}</label>
    </div>
    <br>
    <br>

    {{ partial("partials/submitbutton",['indexurl':'module-http-alert/module-http-alert/index']) }}
</form>