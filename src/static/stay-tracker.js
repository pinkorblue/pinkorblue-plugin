TimeMe.initialize({
    idleTimeoutInSeconds: 15 // time before user considered idle
});

var firstSendSuccess = false;

function sendStay(async) {
    async = typeof async !== 'undefined' ? async : true;
    var timeSpentOnPage = TimeMe.getTimeOnCurrentPageInSeconds();
    data = {
        'interaction_value': timeSpentOnPage,
        'interaction_time': (new Date()).toISOString(),
        'interaction_type': 'stay',
        'test_kpi': test_info.test_kpi,
        'user_id': test_info.user_id,
        'site_name': test_info.site_name,
        'test_id': test_info.test_id,
        'variant_post_id': test_info.variant_post_id
    }
    if (firstSendSuccess) {
        targetUrl = test_info.target_url_edit
        method = "PUT"
    } else {
        data['interaction_id'] = test_info.interaction_id,
        targetUrl = test_info.target_url_create
        method = "POST"
    }
    xmlhttp=new XMLHttpRequest();
    xmlhttp.open(method, targetUrl, async);
    xmlhttp.onload = function (e) {
      if (xmlhttp.readyState === 4) {
        if (xmlhttp.status === 201) {
            firstSendSuccess = true;
        }
        if (xmlhttp.status === 400 && xmlhttp.responseText == "{\"error\":\"Interaction is duplicated.\"}") {
            firstSendSuccess = true;
        }
      }
    };
    xmlhttp.setRequestHeader("Content-type", "application/json");
    xmlhttp.setRequestHeader("Authorization", test_info.authorize);
    xmlhttp.send(JSON.stringify(data));
};

window.onbeforeunload = function(){
    sendStay(false);
}

var initialTimeouts = [1, 3, 7];
initialTimeouts.forEach(function(t) {
    setTimeout(sendStay, t * 1000);
});
setInterval(sendStay, 15000);