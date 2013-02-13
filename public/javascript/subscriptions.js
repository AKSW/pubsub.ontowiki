$(document).ready(function(){
    $("#pubsub-subscriptions-msgBoxNewFeedUpdates").hide();
    $("#subscriptions").hide();
    
    /**
     * You are ALREADY subscribed to the given resource
     * So the topic url is already in the store
     */
    if ("" != topicUrl)
    {
        /**
         * Set events
         */
        $("#pubsub-subscriptions-msgBoxLinkedData").fadeIn("slow");
        $("#subscriptions").fadeIn("slow");
        
        $("#pubsub-subscriptions-msgBoxFeedSubscribed").fadeIn("slow");
        $("#pubsub-subscriptions-buttonSubscribe").click(function(){
            subscribe(topicUrl, "subscribe");
        });
        $("#pubsub-subscriptions-buttonUnSubscribe").click(function(){
            subscribe(topicUrl, "unsubscribe");
        });
        
        // check if there are feed updates for the given topic url
        checkForFeedUpdates(
            selectedResource.URI, 
            function(data){            
                // If there are new feed updates (related files in cache folder)
                if("true" == data) {
                    $("#pubsub-subscriptions-msgBoxNewFeedUpdates").fadeIn("slow");
                    
                    /**
                     * Set click event for button to import feed updates
                     */
                    $("#pubsub-subscriptions-buttonImportFeedUpdates").click(function(){
                        importFeedUpdates(selectedResource.URI);
                    });
                }
        });
    }
    
    /**
     * You are NOT subscribed to the given resource!
     */
    else
    {
        checkResource(
            selectedResource.URI, 
            function(data) {
                
                // result data >> JSON
                resourceInformation = $.parseJSON(data);
                
                // show isLinkedData box if resource is linked data
                if (true == resourceInformation.isLinkedData)
                {
                    $("#subscriptions").fadeIn("slow");
                    $("#pubsub-subscriptions-msgBoxLinkedData").fadeIn("slow");
                }
                
                // get header for given resource uri, first check is with no redirect and
                // if no link is found the second check is with redirect
                askResourceHead(
                    selectedResource.URI,
                    true,
                    function(data){
                        var feedFound = checkFeed(data);
                        if (false === feedFound)
                        {
                            askResourceHead(
                                selectedResource.URI,
                                false,
                                function(data){
                                    checkFeed(data);
                                }
                            );
                        }
                        
                    }
                );
                
            }
        );
    }
});

function checkFeed(data)
{
    foundFeedLink = false;
    // result data >> JSON
    data = $.parseJSON(data);
    
    // normalize array keys
    data = arrayKeysToLower(data);
    
    var tagNumber = 0;
    
    while (tagNumber < headerFeedTags.length && false === foundFeedLink)
    {
        // if the feed tag field in data-array is set
        if (undefined !== data[headerFeedTags[tagNumber]]) {
            foundFeedLink = true;
            
            var subscriptionTopicUrl = data[headerFeedTags[tagNumber]];
                                            
            isSubscriptionTopicUrlResolvable(
                subscriptionTopicUrl,
                function(){ // If it is RESOLVEABLE
                    $("#subscriptions").fadeIn("slow");
                    $("#pubsub-subscriptions-msgBoxLinkedDataAndFeed").fadeIn("slow");
                    
                    // subscribe
                    $("#pubsub-subscriptions-buttonSubscribe").click(function(){
                        subscribe(subscriptionTopicUrl, "subscribe");
                    });
                    
                    // unsubscribe
                    $("#pubsub-subscriptions-buttonUnSubscribe").click(function(){
                        subscribe(subscriptionTopicUrl, "unsubscribe");
                    });
                    
                    if (false == resourceInformation.isLinkedData 
                        && true == resourceInformation.isLocalResource)
                    {
                        $("#pubsub-subscriptions-msgBoxPublish").fadeIn("slow");
                        
                        $("#pubsub-subscriptions-buttonPublish").click(function(){
                            publishSubscriptionTopicUrl(
                                standardPublishHubUrl,
                                subscriptionTopicUrl,
                                function(){
                                    $("#pubsub-subscriptions-msgBoxPublish").fadeOut("slow");
                                }
                            );
                        });
                    }
                }
            );
        }
        tagNumber++;
    }
    return foundFeedLink;
}