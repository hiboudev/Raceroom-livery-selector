history.scrollRestoration = "manual";

var timeoutId = -1;
var synchronizingProfile = false;
var globalUsername = null;
var ajaxManager = new AjaxManager();

document.addEventListener("DOMContentLoaded", function () {
    yall({
        observeChanges: true,
        observeRootSelector: '.thumbnailContainer',
        threshold: 500,
    });
});

function onPageLoaded() {
    var username = CookieManager.getValue('username');
    if (username != null && username != '')
        checkProfile(username.trim(), displayUrlData);
    else {
        setUsername('');
        initializeLoginBox();
        displayUrlData();
    }

    window.onpopstate = handleHistoryChange;
    window.onbeforeunload = cleanBeforeExit;

    checkShopAvailability();
}

function checkShopAvailability() {
    ajaxManager.executeAjax(RequestType.SITE_AVAILABLE,
        {
            url: "checkShopAvailability.php",
            success: function (result) {
                if (result == 1)
                    $('.shopUnavailableMessage').css('display', 'block');
            },
        }
    );
}

function cleanBeforeExit() {
    ajaxManager.abortAll();
}

function handleHistoryChange(event) {
    event.preventDefault();
    displayUrlData();
}

function displayUrlData() {
    if (!urlParamExists("carId") && !urlParamExists("classId"))
        return;

    var carId = getUrlParam("carId");
    var classId = getUrlParam("classId");

    if (isNaN(Number(classId))) classId = null;
    if (isNaN(Number(carId))) carId = null;

    if (classId != null) {
        selectIfExists('carClassSelector', classId);
        if (carId != null) {
            getCars(classId, function () { selectIfExists('carSelector', carId); });
            getCarLiveries(carId);
        }
        else {
            getCars(classId, selectIfUniqueCar);
            getClassLiveries(classId);
        }
    }
    else if (carId != null)
        getCarLiveries(carId);
}

function selectIfExists(selector, optionValue) {
    var optionExists = false;
    $("#" + selector + " > option").each(function () {
        if (this.value == optionValue) {
            optionExists = true;
            return false;
        }
    });
    $("#" + selector).val(optionExists ? optionValue : -1);
}

function selectIfUniqueCar() {
    var carCount = $("#carSelector option").length - 1;
    if (carCount == 1)
        $('#carSelector option')[1].selected = true;
}

function forgetProfile() {
    setUsername("");
    initializeLoginBox();
}

function openLoginFormClicked() {
    $('.openLoginFormLinkContainer').css("display", "none");
    $('#loginForm').css("display", "block");
    $('#profileField').focus();
}

function initializeLoginBox() {
    $('#loginBox').css("display", "block");

    if (globalUsername != "" && globalUsername != null) {
        $('#loggedPrompt').html("Profil : <b>" + globalUsername + "</b>");
        $('#resyncButton').css("display", "inline-block");
        $('.resyncTip').css("display", "block");
        $('.openLoginFormLinkContainer').css("display", "block");
        $('#loginForm').css("display", "none");
        $('#profileField').val("");
        $('.forgetProfileContainer').css("display", "block");
    } else {
        $('#loggedPrompt').html("Profil :");
        $('#resyncButton').css("display", "none");
        $('.resyncTip').css("display", "none");
        $('#loginForm').css("display", "block");
        $('.forgetProfileContainer').css("display", "none");
    }
}

/**
 * handler is optional (default: null), no default value for IE compatibility.
 */
function getCars(classId, handler) {
    if (classId < 0) return;
    $("#carSelector").empty();

    ajaxManager.executeAjax(RequestType.GET_CARS,
        {
            type: "GET",
            url: "r3e_db_api.php",
            data: "dataType=cars&classId=" + classId,
            success: function (result) {
                $("#carSelector").html(result);
                if (handler != null) handler();
            }
        }
    );
};

function classSelected(classId) {
    if (classId < 0) return;

    history.pushState({ 'classId': classId }, '', '?classId=' + classId);
    getCars(classId, selectIfUniqueCar);
    getClassLiveries(classId);
}

function carSelected(carId) {
    var classId = $('#carClassSelector').val();
    if (carId < 0 || classId < 0) return;

    history.pushState({ 'carId': carId }, '', '?classId=' + classId + '&carId=' + carId);
    getCarLiveries(carId);
}

function getCarLiveries(carId) {
    if (carId < 0) return;

    ajaxManager.executeAjax(RequestType.GET_LIVERIES,
        {
            type: "GET",
            url: "r3e_db_api.php",
            data: "dataType=carLiveries&carId=" + carId + "&username=" + globalUsername,
            success: function (result) {
                // empty() seems to fix a bug in IE11 (not displaying images except on first page)
                $("#thumbnailContainer").empty();
                // Since we don't empty content before to do request, scroll is not reseted.
                window.scrollTo(0, 0);
                $("#thumbnailContainer").html(result);
                checkYall();
            }
        }
    );
}

function getClassLiveries(classId) {
    if (classId < 0) return;

    ajaxManager.executeAjax(RequestType.GET_LIVERIES,
        {
            type: "GET",
            url: "r3e_db_api.php",
            data: "dataType=classLiveries&classId=" + classId + "&username=" + globalUsername,
            success: function (result) {
                // empty() seems to fix a bug in IE11 (not displaying images except on first page)
                $("#thumbnailContainer").empty();
                // Since we don't empty content before to do request, scroll is not reseted.
                window.scrollTo(0, 0);
                $("#thumbnailContainer").html(result);
                checkYall();
            }
        }
    );
}

function checkYall() {
    if (!window.MutationObserver) yall();
}

function copyLink(link) {
    $("#linkField").val("[IMG]" + link + "[/IMG]");
    $("#linkField").select();
    if (document.execCommand("copy"))
        notifyCopy();
    else
        alert("Votre configuration n'autorise pas la copie dans le presse-papier, veuillez copier le lien manuellement.");
}

function notifyCopy() {
    $('#notification').css('display', 'inline');
    if (timeoutId != -1) clearTimeout(timeoutId);
    timeoutId = setTimeout(removeNotification, 2000);
}

function removeNotification() {
    $('#notification').css('display', 'none');
}

/**
 * handler is optional (default: null), no default value for IE compatibility.
 */
function checkProfile(username, handler) {
    if (synchronizingProfile) return;
    synchronizingProfile = true;

    $.blockUI({
        message: '<h1>Vérification du profil Raceroom...</h1>',
        css: { backgroundColor: '#fff', color: '#444', 'border-style': 'none' }
    });

    var syncTriggered = false;

    ajaxManager.executeAjax(RequestType.PROFILE_CHECK,
        {
            type: "GET",
            url: "user_profile_api.php",
            data: "checkUsername=" + username,
            success: function (result) {
                synchronizingProfile = false;
                $.unblockUI();

                switch (result) {
                    case '0':
                        setUsername(username);
                        break;
                    case '1':
                        syncTriggered = true;
                        // Doesn't exists in our DB so create profile.
                        synchronizeProfile(username, handler);
                }
            },
            error: function (a, b, c) {
                alert("Une erreur est survenue.");
                // setUsername("");
            },
            complete: function (request, status) {
                if (!syncTriggered) {
                    initializeLoginBox();
                    if (handler != null) handler();
                }
            }
        }
    );
}

function loginClicked(event) {
    event.preventDefault();

    var username = $("#profileField").val().trim();
    if (username != "")
        checkProfile(username);
}

function resyncClicked() {
    if (globalUsername != "" && globalUsername != null)
        synchronizeProfile(globalUsername);
}

function synchronizeProfile(username, handler) {
    if (synchronizingProfile) return;
    synchronizingProfile = true;

    $.blockUI({
        message: '<h1>Synchronisation du profil Raceroom...</h1>',
        css: { backgroundColor: '#fff', color: '#444', 'border-style': 'none' }
    });

    ajaxManager.executeAjax(RequestType.PROFILE_SYNC,
        {
            type: "GET",
            url: "user_profile_api.php",
            data: "username=" + username,
            success: function (result) {
                switch (result) {
                    case '1':
                        alert("L'utilisateur '" + username + "' n'a pas été trouvé sur la boutique Raceroom.");
                        // setUsername("");
                        break;
                    case '2':
                    case '3':
                        alert("Une erreur code '" + result + "' s'est produite.");
                        break;
                    default:
                        setUsername(result);
                }
            },
            error: function (a, b, c) {
                alert("Une erreur est survenue.");
            },
            complete: function () {
                synchronizingProfile = false;
                $.unblockUI();
                initializeLoginBox();
                if (handler != null) handler();
            }
        }
    );
}

function setUsername(username) {
    CookieManager.setValue('username', username);
    globalUsername = username;
    var loginText = username == "" ? "Aucun profil utilisé" : username;
    $('#usernameField').html(loginText);
}

function showProfileHelp() {
    $('.profileHelp').css('display', 'block');
    $('.profileHelpLink').css('display', 'none');
}