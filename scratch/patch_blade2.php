<?php
$bladeFile = '/home/mrapollos/Documents/work/corehealth_v2/resources/views/admin/audit/workbench.blade.php';
$content = file_get_contents($bladeFile);

// 1. Replace the left navigation panel
$leftNavStartStr = '<button type="button" class="audit-tab-btn active" data-target="#tab-dashboard">';
$leftNavEndStr = '</div>';
$leftNavStart = strpos($content, $leftNavStartStr);
$leftNavEnd = strpos($content, '<button type="button" class="audit-tab-btn" data-target="#tab-module-inventory">', $leftNavStart);
$leftNavEnd = strpos($content, '</div>', $leftNavEnd) + 6; // Include closing div

$newLeftNav = '
                <button type="button" class="audit-tab-btn active" data-target="#tab-dashboard">
                    <i class="mdi mdi-view-dashboard-outline"></i> Dashboard Overview
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-staff-receivables">
                    <i class="mdi mdi-account-cash-outline"></i> Staff Bills Ledger
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-financials">
                    <i class="mdi mdi-cash-multiple"></i> Financial & Revenue (A)
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-clinical">
                    <i class="mdi mdi-pulse"></i> Clinical Flow (B)
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-diagnostics">
                    <i class="mdi mdi-microscope"></i> Lab, Imaging & Pharm (C)
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-inventory">
                    <i class="mdi mdi-archive-outline"></i> Inventory & Stores (D)
                </button>
            </div>';

$content = substr_replace($content, ltrim($newLeftNav), $leftNavStart, $leftNavEnd - $leftNavStart);

// 2. Replace the old modules with the new 13 modules
$modulesHTML = file_get_contents('/home/mrapollos/.gemini/antigravity/brain/e3db7e73-2345-492d-8b0b-8892f3610b5c/scratch/modules_html.html');
// Note: modules_html.html has the {{-- Drawer Overlay --}} comment at the end (if I put it there previously). Wait, let's just strip that from modules_html if it exists, or let's use the exact boundary.
$panelsStartStr = '{{-- Panel: Module 1 Financials --}}';
$panelsEndStr = '{{-- Drawer Settings panel --}}'; // This is what it was called in the original file

$panelsStart = strpos($content, $panelsStartStr);
$panelsEnd = strpos($content, $panelsEndStr);

if ($panelsStart !== false && $panelsEnd !== false) {
    // The modulesHTML contains the new modules.
    // I should append </div></div></div> before the drawer settings panel because the old code had:
    //         </form>
    //     </div>
    // </div>
    // </div>
    // {{-- Drawer Settings panel --}}
    
    // I will replace up to $panelsEnd, but I need to make sure I don't delete the closing divs if they are part of the main layout, wait, the old panels had closing divs at the end of the Center Content panels.
    // Let's just find exactly what to replace. The modules are between {{-- Panel: Module 1 Financials --}} and the last </div> before {{-- Drawer Settings panel --}}.
    
    // Let's just replace from $panelsStart to $panelsEnd, and ensure we put back the `</div></div></div>`.
    $replacement = $modulesHTML . "\n        </div>\n    </div>\n</div>\n\n";
    $content = substr_replace($content, $replacement, $panelsStart, $panelsEnd - $panelsStart);
} else {
    echo "Could not find panel boundaries.\n";
}

// 3. Fix the drawer description and welcome message
$content = str_replace('33 worksheets', '13 worksheets', $content);
$content = str_replace('33 core', '13 core', $content);

file_put_contents($bladeFile, $content);
echo "Blade file patched successfully.\n";
