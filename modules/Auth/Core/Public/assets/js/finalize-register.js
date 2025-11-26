(function hardMuteConsoleAndErrors() {
  var MUTE_ALL = true; // désactive les logs JS
  if (!MUTE_ALL) return;
  var noop = function () {};
  var c = (window.console = window.console || {});
  ["log", "info", "warn", "error", "debug", "trace"].forEach(
    (fn) => (c[fn] = noop)
  );
  window.addEventListener(
    "error",
    (e) => {
      e.preventDefault();
      return false;
    },
    true
  );
  window.addEventListener(
    "unhandledrejection",
    (e) => {
      e.preventDefault();
      return false;
    },
    true
  );
})();

// 📱 Normalisation E.164 (UG + DRC)
function normalizePhone(val) {
  if (!val) return "";
  let v = val.trim().replace(/[^\d+]/g, "");
  if (v.startsWith("+256")) return "+256" + v.replace(/\D/g, "").slice(3, 12);
  if (v.startsWith("256")) return "+256" + v.replace(/\D/g, "").slice(3, 12);
  if (v.startsWith("07")) return "+256" + v.replace(/\D/g, "").slice(1, 10);
  if (/^7\d{8,}$/.test(v)) return "+256" + v.replace(/\D/g, "").slice(0, 9);
  if (v.startsWith("+243")) return "+243" + v.replace(/\D/g, "").slice(3, 12);
  if (v.startsWith("243")) return "+243" + v.replace(/\D/g, "").slice(3, 12);
  if (/^0[89]\d+/.test(v)) return "+243" + v.replace(/\D/g, "").slice(1, 10);
  if (/^[89]\d+/.test(v)) return "+243" + v.replace(/\D/g, "").slice(0, 9);
  return v;
}

$(document).ready(function () {
  const phoneInput = document.getElementById("phone_number");
  const flagIcon = document.getElementById("flag-icon");
  const dropdown = document.getElementById("country-dropdown");
  const phoneError = document.getElementById("phone_number_error");
  const wrapper = document.getElementById("phone-wrapper");

  function updateFlag(msisdn) {
    if (msisdn.startsWith("+256")) flagIcon.textContent = "🇺🇬";
    else if (msisdn.startsWith("+243")) flagIcon.textContent = "🇨🇩";
    else flagIcon.textContent = "";
  }

  phoneInput.addEventListener("input", () =>
    updateFlag(normalizePhone(phoneInput.value))
  );

  phoneInput.addEventListener(
    "focus",
    () => (dropdown.style.display = "block")
  );
  document.addEventListener("click", (e) => {
    if (!wrapper.contains(e.target)) dropdown.style.display = "none";
  });

  document.querySelectorAll(".country-option").forEach((opt) => {
    opt.addEventListener("click", function (e) {
      e.stopPropagation();
      phoneInput.value = this.dataset.code + " ";
      flagIcon.textContent = this.dataset.flag;
      dropdown.style.display = "none";
      phoneError.textContent = "";
    });
  });

  // --- Submit ---
  $("#registerForm").on("submit", function (event) {
    event.preventDefault();
    const $submitBtn = $("#custom-login-login");
    const $spinner = $submitBtn.find(".btn-spinner");
    const $btnText = $submitBtn.find(".btn-text");

    if (phoneInput) phoneInput.value = normalizePhone(phoneInput.value);

    $submitBtn.prop("disabled", true);
    $btnText.hide();
    $spinner.show();

    const formData = $(this).serialize();

    $.ajax({
      url: "/finalize-registration-post",
      type: "POST",
      data: formData,
      dataType: "json",
      success: function (response) {
        $spinner.hide();
        $btnText.show();
        $submitBtn.prop("disabled", false);
        if (response.success) {
          localStorage.setItem("justRegistered", "true");
          $("#popupText").text(response.message);
          $("#popupMessage")
            .removeClass("error-popup")
            .addClass("success-popup")
            .show();
          setTimeout(() => location.assign("/auth/sync"), 1000);
        } else {
          let msg =
            typeof response.error === "object"
              ? Object.values(response.error).join("<br>")
              : response.error || "Validation error";
          $("#popupText").html(msg);
          $("#popupMessage")
            .removeClass("success-popup")
            .addClass("error-popup")
            .show();
        }
      },
      error: function (xhr) {
        $spinner.hide();
        $btnText.show();
        $submitBtn.prop("disabled", false);
        let msg = "An error occurred while sending the data.";
        if (xhr.responseJSON)
          msg = xhr.responseJSON.error || xhr.responseJSON.message || msg;
        else if (xhr.responseText) msg = xhr.responseText;
        $("#popupText").html(msg);
        $("#popupMessage")
          .removeClass("success-popup")
          .addClass("error-popup")
          .show();
      },
    });
  });

  $("#closePopup").on("click", () => $("#popupMessage").hide());
});
