
import os

file_path = r'c:\Users\HARDMOTIONS\Documents\work\corehealth_v2\resources\views\admin\patients\show1.blade.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Target string
target = """            <div class="tab-pane fade {{ $section == 'vitalsCardBody' ? 'show active' : '' }}" id="vitalsCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.vitals')</div>
                </div>
            </div>"""

replacement = """            <div class="tab-pane fade {{ $section == 'vitalsCardBody' ? 'show active' : '' }}" id="vitalsCardBody" role="tabpanel">
                <div class="mt-2">
                    @include('admin.partials.unified_vitals', ['patient' => $patient])
                </div>
                 <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var vitalsTab = document.getElementById('vitals-tab');
                        if(vitalsTab){
                             vitalsTab.addEventListener('shown.bs.tab', function (event) {
                                if(window.initUnifiedVitals) {
                                    window.initUnifiedVitals({{ $patient->id }});
                                }
                            });
                             // Handle initial load if tab is active
                            if (vitalsTab.classList.contains('active')) {
                                if(window.initUnifiedVitals) {
                                    window.initUnifiedVitals({{ $patient->id }});
                                }
                            }
                        }
                    });
                 </script>
            </div>"""

# Try direct replacement
if target in content:
    new_content = content.replace(target, replacement)
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Successfully updated " + file_path)
else:
    print("Target string not found directly. Attempting to match with normalized whitespace.")
    # Fallback could be implemented if exact match fails, but let's see.
    # print(content[content.find('id="vitalsCardBody"'):content.find('id="vitalsCardBody"')+200])
