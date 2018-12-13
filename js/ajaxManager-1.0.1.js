var AjaxManager = function () {
    this.activeRequests = {};

    this.executeAjax = function (type, params) {
        this.abortActiveRequest(type);

        var completeFunction = params['complete'];
        var activeRequests = this.activeRequests;
        var privateComplete = function (request, status) {
            delete activeRequests[type];
            if (completeFunction != null) completeFunction(request, status);
        };
        params['complete'] = privateComplete;

        this.activeRequests[type] = $.ajax(params);
    }

    this.abortActiveRequest = function (type) {
        if (this.activeRequests.hasOwnProperty(type)) {
            this.activeRequests[type].abort();
            delete this.activeRequests[type];
        }
    }

    this.abortAll = function () {
        for (var type in this.activeRequests)
            this.abortActiveRequest(type);
    }
};


var RequestType = {
    PROFILE_CHECK: 1,
    PROFILE_SYNC: 2,
    GET_CARS: 3,
    GET_LIVERIES: 4
}