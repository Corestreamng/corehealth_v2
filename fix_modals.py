import os

file_path = r'c:\Users\HARDMOTIONS\Documents\work\corehealth_v2\resources\views\admin\patients\partials\modals.blade.php'

js_code = """
// Initialize Select2 for the edit modal diagnosis selection
$(document).ready(function() {
    if ($('#editEncounterReasons').length > 0) {
        $('#editEncounterReasons').select2({
            placeholder: "Select Reason(s)",
            allowClear: true,
            tags: true, // Allow custom reasons
            dropdownParent: $('#editEncounterModal') // Important for Select2 in Bootstrap modal
        });
    }

    // Handle Not Applicable Checkbox
    $('#editEncounterNotApplicable').on('change', function() {
        const isChecked = $(this).is(':checked');
        if (isChecked) {
            $('#editEncounterReasonsGroup').hide();
            $('#editEncounterCommentsGroup').hide();
            $('#editEncounterReasons').val(null).trigger('change');
            $('#editEncounterComment1').val('NA');
            $('#editEncounterComment2').val('NA');
        } else {
            $('#editEncounterReasonsGroup').show();
            $('#editEncounterCommentsGroup').show();
        }
    });
});
"""

try:
    with open(file_path, 'rb') as f:
        content = f.read()

    search_tag = b'</script>'
    last_index = content.rfind(search_tag)

    if last_index == -1:
        print("Error: </script> tag not found in the file.")
    else:
        # Keep content up to the start of the last </script>
        pre_script_content = content[:last_index]

        # The tag itself
        script_tag_content = content[last_index:last_index + len(search_tag)]

        # Construct new content
        # Ensure we add a newline before the JS code if needed, and the JS code itself
        new_content = pre_script_content + js_code.encode('utf-8') + script_tag_content

        with open(file_path, 'wb') as f:
            f.write(new_content)

        print("File updated successfully.")

        # Verify
        with open(file_path, 'rb') as f:
            final_content = f.read()
            if final_content.endswith(b'</script>'):
                print("Verification successful: File ends with </script>")
            else:
                print(f"Verification failed: File ends with {final_content[-20:]}")

except Exception as e:
    print(f"An error occurred: {e}")
