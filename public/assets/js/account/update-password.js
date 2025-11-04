$(document).ready(function () {
  $("#profile-form").submit(function (e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $("#submit-button").prop("disabled", true);
    $("#loading-icon").show();

    $.ajax({
      url: "/account/update-password",
      type: "POST",
      data: formData,
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $("#success-message").text(response.success);
          $(".profile-alert")
            .removeClass("alert-danger")
            .addClass("alert-success")
            .show();
        } else if (response.error) {
          $("#success-message").text(response.error);
          $(".profile-alert")
            .removeClass("alert-success")
            .addClass("alert-danger")
            .show();
        }
      },
      error: function (xhr, status, error) {
        console.log("Erreur AJAX : ", error);

        let errorMessage =
          "Une erreur est survenue. Veuillez réessayer plus tard.";
        if (xhr.responseJSON && xhr.responseJSON.error) {
          errorMessage = xhr.responseJSON.error;
        }

        $("#success-message").text(errorMessage);
        $(".profile-alert")
          .removeClass("alert-success")
          .addClass("alert-danger")
          .show();
      },
      complete: function () {
        $("#loading-icon").hide();
        $("#submit-button").prop("disabled", false);
      },
    });
  });
});
