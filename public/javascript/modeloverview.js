$(document).ready(function(){
    
    /**
     * Import feed updates
     */
    $("#pubsub-modeloverview-msgBoxNewFeedUpdates").click(function(){
        importFeedUpdatesForModelResources(function(){
            
            // fade out new feed update box
            $("#pubsub-modeloverview-msgBoxNewFeedUpdates").fadeOut("slow", function(){
                
                // after that fade in success message
                $("#pubsub-modeloverview-msgBoxFeedUpdatesImported").fadeIn("slow");
            });
        });
    });
});
