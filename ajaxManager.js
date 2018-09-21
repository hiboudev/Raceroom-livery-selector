class AjaxManager {

    constructor () {
        this.activeRequests = {};
    }

    executeAjax (type, params) {
        this.abortActiveRequest(type);

        var completeFunction = params['complete'];
        var activeRequests = this.activeRequests;
        var privateComplete = function (request) {
            delete activeRequests[type];
        };
        params['complete'] = function (request, status) {privateComplete(request); if (completeFunction != null) completeFunction(request, status)};

        this.activeRequests[type] = $.ajax(params);
    }

    abortActiveRequest (type) {
        if (this.activeRequests.hasOwnProperty(type)) {
            var request = this.activeRequests[type];
            request.abort();
            delete this.activeRequests[type];
        }
    }

    abortAll () {
        for(var type in this.activeRequests)
            this.abortActiveRequest(type);
    }
}

class RequestType {
    static get PROFILE_CHECK () {return 1;}
    static get PROFILE_SYNC () {return 2;}
    static get GET_CARS () {return 3;}
    static get GET_LIVERIES () {return 4;}
}