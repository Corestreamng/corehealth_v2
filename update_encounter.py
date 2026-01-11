
import os

file_path = r'c:\Users\HARDMOTIONS\Documents\work\corehealth_v2\resources\views\admin\doctors\new_encounter.blade.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

start_marker = '<div class="tab-pane fade" id="vitals" role="tabpanel" aria-labelledby="vitals_tab">'
end_marker = '<div class="tab-pane fade" id="laboratory_services"'

start_idx = content.find(start_marker)
end_idx = content.find(end_marker)

if start_idx != -1 and end_idx != -1:
    new_content = """<div class="tab-pane fade" id="vitals" role="tabpanel" aria-labelledby="vitals_tab">
            <div class="mt-2">
                @include('admin.partials.unified_vitals', ['patient' => $patient])
            </div>
            <div class="card mt-2 border-0">
                 <div class="card-body px-0">
                    <button type="button" onclick="switch_tab(event,'patient_data_tab')"
                        class="btn btn-secondary mr-2">
                        Prev
                    </button>
                    <button type="button" onclick="switch_tab(event,'nursing_notes_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                 </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var vitalsTab = document.getElementById('vitals_data_tab');
                    if(vitalsTab){
                         // Bootstrap 5
                        vitalsTab.addEventListener('shown.bs.tab', function (event) {
                            if(window.initUnifiedVitals) {
                                window.initUnifiedVitals({{ $patient->id }});
                            }
                        });
                        // Fallback/Others
                        $(vitalsTab).on('shown.bs.tab', function (e) {
                             if(window.initUnifiedVitals) {
                                window.initUnifiedVitals({{ $patient->id }});
                            }
                        });
                    }
                });
            </script>
        </div>
        """

    updated_content = content[:start_idx] + new_content + content[end_idx:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(updated_content)
    print('Successfully updated ' + file_path)
else:
    print('Markers not found')
