jQuery(document).ready(function($) {
    function updateCheckboxValue(checkbox, hiddenInputId) {
        var isChecked = checkbox.is(':checked');
        console.log('Checkbox ' + checkbox.attr('id') + ' checked: ' + isChecked);
        $('#' + hiddenInputId).val(isChecked ? 'true' : 'false');
    }

    function toggleCheckboxes(checkbox, type) {
        var methodId = checkbox.attr('id').replace(type, '');
        console.log('Toggling checkbox for method: ' + methodId + ' with type: ' + type);
        updateCheckboxValue(checkbox, methodId + type + '_value');

        var oppositeType = type === '_percentage_enabled' ? '_fixed_enabled' : '_percentage_enabled';
        var oppositeCheckbox = $('input[id="' + methodId + oppositeType + '"]');

        if (checkbox.is(':checked')) {
            if (oppositeCheckbox.length) {
                oppositeCheckbox.prop('checked', false);
                updateCheckboxValue(oppositeCheckbox, methodId + oppositeType + '_value');
            }
        }
    }

    $('input[id$="_percentage_enabled"], input[id$="_fixed_enabled"]').on('change', function() {
        var type = $(this).attr('id').includes('_percentage_enabled') ? '_percentage_enabled' : '_fixed_enabled';
        toggleCheckboxes($(this), type);
    });

    $('input[type="checkbox"]').each(function() {
        var hiddenInputId = $(this).attr('id') + '_value';
        updateCheckboxValue($(this), hiddenInputId);
    });
});
