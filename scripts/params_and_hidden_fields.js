<!-- Скрипт для заполнения скрытых полей в лид-формах на Tilda и кастомных параметров в Marquiz -->
<script>
var CONFIG = {
    yandexMetrikaCounterId: '97499094',
    googleMeasurementId: 'G-FSSX2KXR1L',
    visitorIdCookieName: '_cmg_csst22c7F',
    roistatCookieName: 'roistat_visit',
    fromValueTilda: 'Tilda',
    fromValueMarquiz: 'Marquiz'
};

var getCookie = function(name) {
    var value = '; ' + document.cookie;
    var parts = value.split('; ' + name + '=');
    return parts.length === 2 ? parts.pop().split(';').shift() : '';
};

var getIdentifiers = function() {
    return {
        visitor_uid: window.AMOPIXEL_IDENTIFIER ? AMOPIXEL_IDENTIFIER.getVisitorUid() : '',
        visitor_id: getCookie(CONFIG.visitorIdCookieName) || ''
    };
};

var getYandexMetrikaIds = function() {
    return new Promise(function(resolve) {
        if (typeof window.ym === 'undefined') {
            resolve({ ym_uid: '', ym_counter: '' });
        } else {
            window.ym(CONFIG.yandexMetrikaCounterId, 'getClientID', function(ym_uid) {
                resolve({ ym_uid: ym_uid, ym_counter: CONFIG.yandexMetrikaCounterId });
            });
        }
    });
};

var getGoogleClientIdFromCookie = function() {
    var gClientId = getCookie('_ga');
    if (gClientId) {
        var parts = gClientId.split('.');
        return parts.length > 2 ? parts[2] + '.' + parts[3] : '';
    }
    return '';
};

var getRoistatVisitId = function() {
    return Promise.resolve(getCookie(CONFIG.roistatCookieName));
};

var getQueryParams = function() {
    var params = new URLSearchParams(window.location.search);
    return {
        utm_content: params.get('utm_content') || localStorage.getItem('utm_content') || '',
        utm_medium: params.get('utm_medium') || localStorage.getItem('utm_medium') || '',
        utm_campaign: params.get('utm_campaign') || localStorage.getItem('utm_campaign') || '',
        utm_source: params.get('utm_source') || localStorage.getItem('utm_source') || '',
        utm_term: params.get('utm_term') || localStorage.getItem('utm_term') || '',
        utm_referrer: params.get('utm_referrer') || localStorage.getItem('utm_referrer') || '',
        openstat_service: params.get('openstat_service') || localStorage.getItem('openstat_service') || '',
        openstat_campaign: params.get('openstat_campaign') || localStorage.getItem('openstat_campaign') || '',
        openstat_ad: params.get('openstat_ad') || localStorage.getItem('openstat_ad') || '',
        openstat_source: params.get('openstat_source') || localStorage.getItem('openstat_source') || '',
        gclid: params.get('gclid') || localStorage.getItem('gclid') || '',
        yclid: params.get('yclid') || localStorage.getItem('yclid') || '',
        fbclid: params.get('fbclid') || localStorage.getItem('fbclid') || '',
        rb_clickid: params.get('rb_clickid') || localStorage.getItem('rb_clickid') || '',
        from: CONFIG.fromValueTilda
    };
};

var fillHiddenFields = function(allParams) {
    document.querySelectorAll('form').forEach(function(form) {
        Object.keys(allParams).forEach(function(key) {
            var hiddenField = form.querySelector('input[name="' + key + '"]');
            if (hiddenField) hiddenField.value = allParams[key];
        });
    });
};

var addMarquizParams = function(params) {
    document.addEventListener("marquizLoaded", function() {
        params.from = CONFIG.fromValueMarquiz;
        Object.keys(params).forEach(function(key) {
            Marquiz.addParam(key, params[key]);
        });
    });
};

var storeQueryParams = function() {
    var params = getQueryParams();
    Object.keys(params).forEach(function(key) {
        if (params[key]) localStorage.setItem(key, params[key]);
    });
};

var initialize = function() {
    storeQueryParams();

    var queryParams = getQueryParams();
    var identifiers = getIdentifiers();
    var referrer = document.referrer || '';

    Promise.allSettled([getYandexMetrikaIds(), getRoistatVisitId()])
        .then(function(results) {
            var ymData = results[0].status === 'fulfilled' ? results[0].value : { ym_uid: '', ym_counter: '' };
            var roistatVisitId = results[1].status === 'fulfilled' ? results[1].value : '';

            var gclientid = getGoogleClientIdFromCookie();

            var allParams = Object.assign({}, queryParams, identifiers, {
                _ym_uid: ymData.ym_uid,
                _ym_counter: ymData.ym_counter,
                gclientid: gclientid,
                referrer: referrer,
                roistat: roistatVisitId
            });

            fillHiddenFields(allParams);
            addMarquizParams(allParams);
        })
        .catch(function() {
            var allParams = Object.assign({}, queryParams, identifiers, { referrer: referrer });
            fillHiddenFields(allParams);
            addMarquizParams(allParams);
        });
};

window.addEventListener('DOMContentLoaded', function() {
    setTimeout(initialize, 3000);
});
</script>
<!-- Конец скрипта для заполнения скрытых полей в лид-формах на Tilda и кастомных параметров в Marquiz -->
