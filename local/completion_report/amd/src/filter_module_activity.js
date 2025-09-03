// Path: local/completion_report/amd/src/filter_module_activity.js
define(['jquery'], function($) {
    return {
        init: function() {
            console.log('JavaScript initialized'); // Debugging line to ensure JS is loaded

            // Course select change event
            $('#id_coursename').on('change', function() {
                console.log('Course selected: ', $(this).val()); // Debugging line
                var courseId = $(this).val();
                updateSections(courseId);
            });

            // Section select change event
            $('#id_sectionname').on('change', function() {
                console.log('Section selected: ', $(this).val()); // Debugging line
                var sectionId = $(this).val();
                var courseId = $('#id_coursename').val();
                updateModules(courseId, sectionId);
            });

            function updateSections(courseId) {
                // Clear current options
                $('#id_sectionname').empty().append('<option value="">' + M.util.get_string('select_section', 'local_completion_report') + '</option>');
                $('#id_modulename').empty().append('<option value="">' + M.util.get_string('select_module_activity', 'local_completion_report') + '</option>');

                if (!courseId) {
                    return;
                }

                $.ajax({
                    url: M.cfg.wwwroot + '/local/completion_report/get_sections.php',
                    type: 'GET',
                    data: { courseid: courseId },
                    success: function(data) {
                        console.log('Sections data: ', data); // Debugging line
                        var sections = JSON.parse(data);
                        $.each(sections, function(index, value) {
                            $('#id_sectionname').append($('<option>').val(index).text(value));
                        });
                    },
                    error: function(xhr, status, error) {
                        console.log('Error fetching sections: ', error); // Debugging line
                    }
                });
            }

            function updateModules(courseId, sectionId) {
                // Clear current options
                $('#id_modulename').empty().append('<option value="">' + M.util.get_string('select_module_activity', 'local_completion_report') + '</option>');

                if (!courseId || !sectionId) {
                    return;
                }

                $.ajax({
                    url: M.cfg.wwwroot + '/local/completion_report/get_modules.php',
                    type: 'GET',
                    data: { courseid: courseId, sectionid: sectionId },
                    success: function(data) {
                        console.log('Modules data: ', data); // Debugging line
                        var modules = JSON.parse(data);
                        $.each(modules, function(index, value) {
                            $('#id_modulename').append($('<option>').val(index).text(value));
                        });
                    },
                    error: function(xhr, status, error) {
                        console.log('Error fetching modules: ', error); // Debugging line
                    }
                });
            }
        }
    };
});
