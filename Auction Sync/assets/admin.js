jQuery(document).ready(function ($) {
  $("#sync-auctions").on("click", function () {
      let syncButton = $(this);
      let syncStatus = $("#sync-status");

      syncButton.prop("disabled", true);
      syncStatus.html('<p style="color:blue;">Syncing...</p>');

      $.post(ajaxurl, { action: "sync_auction_data" }, function (response) {
          if (response.success) {
              syncStatus.html('<p style="color:green;">' + response.data.message + '</p>');
          } else {
              syncStatus.html('<p style="color:red;">Error: ' + response.data.error + '</p>');
          }
      })
      .fail(function () {
          syncStatus.html('<p style="color:red;">Request failed.</p>');
      })
      .always(function () {
          syncButton.prop("disabled", false);
      });
  });
});
