// 
function arrayKeysToLower(data) {
    for ( var index in data) {
        data[index.toLowerCase()] = data[index];
    }
    return data;
}

// get header for the given resource, decide if using redirects or not
function askResourceHead(selectedResourceUri, noredirect, callback) {
    $.ajax({
        url: urlBase + 'resource/head/',
        data: {r : selectedResourceUri, noredirect: noredirect}
    }).error(function ( xhr ) {
        console.log(xhr);
    }).done(function ( data, textStatus, jqXHR ) {
    }).success(function ( data, textStatus, jqXHR ) {
        callback(data);
    });
}
 
// check the feed for new updates
function checkForFeedUpdates(selectedResourceUri, callback) {
    // check if there are feed updates for the given topic url
    $.ajax({
        url: urlBase + 'pubsub/existsfeedupdates/',
        data: {r : selectedResourceUri}
    }).error(function ( xhr ) {
    }).done(function ( data, textStatus, jqXHR ) {
    }).success(function ( data, textStatus, jqXHR ){
        callback(data);
    });
}

// 
function checkResource(selectedResourceUri, callback) {
    $.ajax({
        url: urlBase + 'pubsub/checkresource/',
        datatype: 'JSON',
        data: {r : selectedResource.URI}
    }).error(function ( xhr ) {
    }).done(function ( data, textStatus, jqXHR ) {
    }).success(function ( data, textStatus, jqXHR ) {
        callback(data);
    });
}

// import feed updates for the given selected resource uri
function importFeedUpdates(selectedResourceUri) {
    $.ajax({
        url: urlBase + 'pubsub/importfeedupdates/',
        data: {r : selectedResourceUri},
        dataType: 'JSON'
    }).error(function ( xhr ) {
    }).done(function ( data, textStatus, jqXHR ) {
    }).success(function ( data, textStatus, jqXHR ){
        if ("true" === data) {
            $("#pubsub-subscriptions-msgBoxNewFeedUpdates").fadeOut("slow");
            location.reload();
        }
    });
}

// check if given subscription topic url is resolvable
function isSubscriptionTopicUrlResolvable(subscriptionTopicUrl, callbackOnSuccess) {
    $.ajax({
        url: subscriptionTopicUrl,
        datatype: 'xml'
    }).error(function ( xhr ) {
        console.log(xhr);
    }).success(function ( data, textStatus, jqXHR ) {
        callbackOnSuccess();
    });
}

// publish to a given hub and a subscription topic url
function publishSubscriptionTopicUrl(hubUrl, subscriptionTopicUrl, callback) {
    $.ajax({
        url: urlBase + 'pubsub/publish/',
        data: { hubUrl : hubUrl, topicUrl : subscriptionTopicUrl },
        dataType: 'JSON'
    }).error(function ( xhr ) {
        console.log(xhr);
    }).success(function ( data, textStatus, jqXHR ) {
        callback();
    });
}

//
function subscribe(topicUrl, mode) {
    $.ajax({
        url: urlBase + 'pubsub/subscription',
        data:{
            hubUrl : standardHubUrl,
            topicUrl : topicUrl,
            callBackUrl : callbackUrl,
            subscriptionMode : mode,
            verifyMode : 'sync',
            sourceResource : selectedResource.URI
        }
    }).error(function ( xhr ) {
        console.log(xhr);
    }).success(function ( data, textStatus, jqXHR ) {
        if ('subscribe' == mode)
        {
            $('#pubsub-subscriptions-msgBoxLinkedDataAndFeed').fadeOut('slow', function() {
                $('#pubsub-subscriptions-msgBoxFeedSubscribed').fadeIn('slow');
            });
        }
        else
        {
            $('#pubsub-subscriptions-msgBoxFeedSubscribed').fadeOut('slow', function() {
                $('#pubsub-subscriptions-msgBoxLinkedDataAndFeed').fadeIn('slow');
            });
        }
        //console.log(data);
    }).done(function ( data, textStatus, jqXHR ) {
    });
}