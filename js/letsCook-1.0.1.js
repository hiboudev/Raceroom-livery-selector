var CookieManager = function () { };

CookieManager.getValue = function (key) {
    var matches = document.cookie.match(new RegExp(key + "\\s*=\\s*([^;]+)"));
    if (matches != null) return matches[1];
    return null;
}

CookieManager.setValue = function (key, value) {
    document.cookie = key + "=" + value + ";expires=" + new Date(2022, 0).toUTCString();
}