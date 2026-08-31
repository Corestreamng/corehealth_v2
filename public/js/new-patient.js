function switch_tab(e, id_of_next_tab) {
    if (e && e.preventDefault) { e.preventDefault(); }
    $('#' + id_of_next_tab).click();
}

function togglePrincipalSelect() {
    var isPrincipalEl = document.getElementById('is_family_principal');
    var principalSelectGroup = document.getElementById('principal_select_group');
    var principalSelect = document.getElementById('principal_id');

    if (!isPrincipalEl || !principalSelectGroup) return;

    if (isPrincipalEl.checked) {
        principalSelectGroup.style.display = 'none';
        if (principalSelect) principalSelect.value = '';
    } else {
        principalSelectGroup.style.display = 'block';
    }
}

function toggleHMOPrincipalSelect() {
    var isPrincipalEl = document.getElementById('is_hmo_principal');
    var dependentFields = document.querySelectorAll('.hmo-dependent-fields');
    var principalSelect = document.getElementById('hmo_principal_id');
    var dependentRole = document.getElementById('hmo_dependent_role');

    if (!isPrincipalEl) return;

    if (isPrincipalEl.checked) {
        dependentFields.forEach(function(el) { el.style.display = 'none'; });
        if (principalSelect) {
            principalSelect.value = '';
            if (window.jQuery && jQuery().select2) {
                $('#hmo_principal_id').trigger('change');
            }
        }
        if (dependentRole) dependentRole.value = '';
    } else {
        dependentFields.forEach(function(el) { el.style.display = 'block'; });
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    togglePrincipalSelect();
    toggleHMOPrincipalSelect();
    if (window.jQuery && jQuery().select2) {
        $('#principal_id').select2();
        $('#hmo_principal_id').select2();
    }
});
