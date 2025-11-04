function formatPhoneNumber(input) {
  let phoneNumber = input.value.trim();

  if (phoneNumber[0] === "+") {
    phoneNumber = "+" + phoneNumber.slice(1).replace(/\D/g, "");
  } else {
    phoneNumber = phoneNumber.replace(/\D/g, "");
  }
  phoneNumber = phoneNumber.replace(/(\d{3})(?=\d)/g, "$1 ");
  if (phoneNumber.length > 16) {
    phoneNumber = phoneNumber.substring(0, 16);
  }
  input.value = phoneNumber;
}

$(document).ready(function () {
  $("#profile-form").submit(function (e) {
    e.preventDefault();

    var formData = $(this).serialize();

    $("#submit-button").prop("disabled", true);
    $("#loading-icon").show();
    $.ajax({
      url: "/account/edit-profile",
      type: "POST",
      data: formData,
      dataType: "json",
      success: function (response) {
        if (response.success) {
          $("#success-message").text(response.message);
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
        $("#success-message").text(
          "An error occurred. Please try again later."
        );
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
