$(document).ready(function () {
  $("#profile-form").submit(function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.set("show_city", $("#show-city").prop("checked") ? 1 : 0);

    $.ajax({
      url: "/api/get-location",
      type: "GET",
      dataType: "json",
      success: function (userLocationData) {
        var requestUrl = "/api/update-location";
        var requestType = "POST";

        if (!userLocationData || !userLocationData.country_id) {
          requestUrl = "/api/create-location";
          requestType = "POST";
        }
        $.ajax({
          url: requestUrl,
          type: requestType,
          data: formData,
          processData: false,
          contentType: false,
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
        });
      },
      error: function (xhr, status, error) {
        console.error(
          "Erreur lors de la récupération de la localisation de l'utilisateur",
          error
        );
        $("#success-message").text(
          "An error occurred. Please try again later."
        );
        $(".profile-alert")
          .removeClass("alert-success")
          .addClass("alert-danger")
          .show();
      },
    });
  });
});

document.querySelector(".slider").addEventListener("click", function () {
  const checkbox = document.getElementById("show-city");
  checkbox.checked = !checkbox.checked;
});

async function fetchCountries() {
  try {
    const response = await fetch("/api/get-countries");
    const data = await response.json();

    const dropdown = document.getElementById("dropdown-countries");
    dropdown.innerHTML = "";

    const inputFieldCountries = document.getElementById(
      "input-field-countries"
    );
    const hiddenCountryId = document.getElementById("hidden-country-id");
    const inputFieldCity = document.getElementById("input-field-city");
    const hiddenCityId = document.getElementById("hidden-city-id");

    // 🔁 On récupère la position actuelle de l'utilisateur
    const userLocationResponse = await fetch("/api/get-location");
    const userLocationData = await userLocationResponse.json();
    const defaultCountryId = userLocationData?.country_id;
    const defaultCityId = userLocationData?.city_id;

    // ✅ État du switch 'show city'
    if (typeof userLocationData.show_city !== "undefined") {
      const showCityCheckbox = document.getElementById("show-city");
      showCityCheckbox.checked = userLocationData.show_city == 1;
    }

    // 🔁 Création des options pays
    data.countries.forEach((country) => {
      const item = document.createElement("div");
      item.classList.add("dropdown-item");
      item.dataset.id = country.id;

      // Si ce pays est celui de l'utilisateur → on met 'selected'
      if (parseInt(country.id) === parseInt(defaultCountryId)) {
        item.classList.add("selected");
      }

      item.innerHTML = `
        <div class="text">
          <strong>${country.name}</strong>
        </div>
        <div class="icon">
          <i class="fa fa-circle-o"></i>
        </div>
      `;

      item.addEventListener("click", function () {
        // Remplir le champ pays
        inputFieldCountries.value = country.name;
        hiddenCountryId.value = country.id;

        // Réinitialiser ville
        inputFieldCity.value = "";
        hiddenCityId.value = "";

        // Fermer le dropdown pays
        dropdown.style.display = "none";

        // Charger les villes du nouveau pays
        fetchCitiesByCountryId(country.id);
      });

      dropdown.appendChild(item);
    });

    // Préremplir si l'utilisateur a déjà une position
    if (defaultCountryId) {
      // Sélectionne automatiquement le champ input
      const selectedItem = dropdown.querySelector(
        `[data-id="${defaultCountryId}"]`
      );
      if (selectedItem) {
        inputFieldCountries.value = selectedItem
          .querySelector(".text")
          .textContent.trim();
        hiddenCountryId.value = defaultCountryId;
      }

      // Charger les villes du pays sélectionné
      await fetchCitiesByCountryId(defaultCountryId);

      // Et sélectionner automatiquement la ville si dispo
      if (defaultCityId) {
        selectCity(defaultCityId);
      }
    }
  } catch (error) {
    console.error("Erreur lors de la récupération des pays :", error);
  }
}

async function fetchCitiesByCountryId(countryId) {
  try {
    const response = await fetch(`/api/get-cities/${countryId}`);
    const data = await response.json();

    const dropdown = document.getElementById("dropdown-city");
    dropdown.innerHTML = "";

    const inputFieldCity = document.getElementById("input-field-city");
    const hiddenCityId = document.getElementById("hidden-city-id");

    const userLocationResponse = await fetch("/api/get-location");
    const userLocationData = await userLocationResponse.json();
    const defaultCityId = userLocationData?.city_id;

    if (data.cities.length > 0) {
      data.cities.forEach((city) => {
        const item = document.createElement("div");
        item.classList.add("dropdown-item");
        item.dataset.id = city.id;

        // ✅ Ajout d’un style 'selected' si c’est la ville de l’utilisateur
        if (parseInt(city.id) === parseInt(defaultCityId)) {
          item.classList.add("selected");
        }

        // ✅ Icône personnalisée ou emoji (peut être remplacé par une image)
        item.innerHTML = `
          <div class="text">
            <strong>${city.name}</strong>
          </div>
          <div class="icon">
            <i class="fa fa-circle-o"></i>
          </div>
        `;

        item.addEventListener("click", function () {
          if (inputFieldCity) {
            inputFieldCity.value = city.name;
          }
          if (hiddenCityId) {
            hiddenCityId.value = city.id;
          }

          const showCityCheckbox = document.getElementById("show-city");
          if (city.show_city === 1) {
            showCityCheckbox.checked = true;
          }

          // ✅ Retirer tous les 'selected' avant d'ajouter
          const allItems = dropdown.getElementsByClassName("dropdown-item");
          Array.from(allItems).forEach((el) => el.classList.remove("selected"));
          item.classList.add("selected");

          dropdown.style.display = "none";
        });

        dropdown.appendChild(item);
      });

      // Sélection visuelle automatique si ville déjà enregistrée
      if (defaultCityId) {
        selectCity(defaultCityId);
      }
    } else {
      dropdown.innerHTML = '<div class="dropdown-item">No city found</div>';
    }
  } catch (error) {
    console.error(
      "Erreur lors de la récupération des données des villes :",
      error
    );
  }
}

function selectCity(cityId) {
  const dropdownCity = document.getElementById("dropdown-city");
  const cityItems = dropdownCity.getElementsByClassName("dropdown-item");

  Array.from(cityItems).forEach((item) => {
    const itemCityId = item.dataset.id;

    // Retire la classe 'selected' de tous les items d'abord
    item.classList.remove("selected");

    if (parseInt(itemCityId) === parseInt(cityId)) {
      const inputFieldCity = document.getElementById("input-field-city");
      const hiddenCityId = document.getElementById("hidden-city-id");

      const cityName = item.querySelector(".text").textContent.trim();

      if (inputFieldCity) {
        inputFieldCity.value = cityName;
      }
      if (hiddenCityId) {
        hiddenCityId.value = itemCityId;
      }

      item.classList.add("selected");

      document.getElementById("dropdown-city").style.display = "none";
    }
  });
}

document
  .getElementById("input-field-countries")
  .addEventListener("click", function () {
    const dropdown = document.getElementById("dropdown-countries");
    const icon = document.getElementById("icon-countries");
    dropdown.style.display =
      dropdown.style.display === "block" ? "none" : "block";
    icon.classList.toggle("down", dropdown.style.display === "block");
  });

document
  .getElementById("input-field-city")
  .addEventListener("click", function () {
    const dropdown = document.getElementById("dropdown-city");
    const icon = document.getElementById("icon-city");
    dropdown.style.display =
      dropdown.style.display === "block" ? "none" : "block";
    icon.classList.toggle("down", dropdown.style.display === "block");
  });

document.addEventListener("click", function (event) {
  const inputFieldCountries = document.getElementById("input-field-countries");
  const dropdownCountries = document.getElementById("dropdown-countries");
  const inputFieldCity = document.getElementById("input-field-city");
  const dropdownCity = document.getElementById("dropdown-city");

  if (
    !inputFieldCountries.contains(event.target) &&
    !dropdownCountries.contains(event.target)
  ) {
    dropdownCountries.style.display = "none";
  }
  if (
    !inputFieldCity.contains(event.target) &&
    !dropdownCity.contains(event.target)
  ) {
    dropdownCity.style.display = "none";
  }
});

window.onload = function () {
  fetchCountries();
};
