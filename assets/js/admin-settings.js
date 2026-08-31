(function () {
	var select = document.getElementById("wcac_auth_method");
	var row = document.querySelector(".wcac-api-key-header");

	if (select && row) {
		function toggleHeaderRow() {
			row.hidden = select.value !== "api_key";
		}

		select.addEventListener("change", toggleHeaderRow);
		toggleHeaderRow();
	}

	var tbody = document.getElementById("wcac-mapping-rows");
	var addButton = document.getElementById("wcac-add-mapping-row");
	var template = document.getElementById("wcac-mapping-row-template");

	if (!tbody || !addButton || !template) {
		return;
	}

	function nextIndex() {
		var max = -1;
		tbody.querySelectorAll("select[name^='wcac_mapping']").forEach(function (selectEl) {
			var match = selectEl.name.match(/^wcac_mapping\[(\d+)\]/);
			if (match) {
				max = Math.max(max, parseInt(match[1], 10));
			}
		});
		return max + 1;
	}

	addButton.addEventListener("click", function () {
		var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex()));
		tbody.insertAdjacentHTML("beforeend", html);
	});

	tbody.addEventListener("click", function (event) {
		var button = event.target.closest(".wcac-remove-mapping-row");
		if (!button) {
			return;
		}

		var message = window.wcacAdminSettings && window.wcacAdminSettings.confirmRemoveMappingRow
			? window.wcacAdminSettings.confirmRemoveMappingRow
			: "Remove this mapping row?";

		if (!window.confirm(message)) {
			return;
		}

		var mappingRow = button.closest("tr");
		if (mappingRow) {
			mappingRow.remove();
		}

		if (!tbody.querySelector("tr")) {
			addButton.click();
		}
	});
})();
