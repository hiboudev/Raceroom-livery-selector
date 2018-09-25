function getUrlParam(name){
    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
    if (results==null){
       return null;
    }
    else{
       return decodeURI(results[1]) || 0;
    }
}

function urlParamExists(name) {
    return new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href) != null;
}