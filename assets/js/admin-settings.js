(function () {
	var select = document.getElementById("wcac_auth_method");
	var row = document.querySelector(".wcac-api-key-header");

	if (!select || !row) {
		return;
	}

	function toggleHeaderRow() {
		row.hidden = select.value !== "api_key";
	}

	select.addEventListener("change", toggleHeaderRow);
	toggleHeaderRow();
})();
