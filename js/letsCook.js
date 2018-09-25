class Cookie {

    static getValue (key) {
        var matches = document.cookie.match(new RegExp(key+"\\s*=\\s*([^;]+)"));
        if (matches != null) return matches[1];
        return null;
    }

    static setValue (key, value) {
        document.cookie = key+"="+value+";expires="+new Date(2022, 0).toUTCString();
    }
}