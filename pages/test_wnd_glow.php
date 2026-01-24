<?php
$page_title = "WND Glow Test";
require_once "../includes/header.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WND Glow Test</title>
    <link rel="stylesheet" href="../assets/css/doer_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #0f172a;
            color: #e2e8f0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .test-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .test-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .test-header h1 {
            color: #60a5fa;
            margin-bottom: 10px;
        }
        .test-section {
            margin-bottom: 50px;
        }
        .test-section h2 {
            color: #94a3b8;
            margin-bottom: 20px;
            border-bottom: 2px solid #334155;
            padding-bottom: 10px;
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 30px;
            padding: 20px 0;
        }
        .test-card {
            background: #1e293b;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #334155;
            overflow: visible;
            position: relative;
        }
        .test-card .stat-card {
            overflow: visible !important;
            margin: 30px 0;
            padding: 30px;
        }
        /* Ensure glow is visible in test cards */
        .test-card .stat-card::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            z-index: -2;
            pointer-events: none;
            filter: blur(30px);
            opacity: 0.8;
        }
        /* Default grey glow for WND */
        .test-card .stat-card[data-stat="wnd"]::after {
            background: radial-gradient(circle, rgba(55, 71, 79, 0.8) 0%, transparent 70%);
        }
        /* Default grey glow for WND On Time */
        .test-card .stat-card[data-stat="wnd_on_time"]::after {
            background: radial-gradient(circle, rgba(84, 110, 122, 0.8) 0%, transparent 70%);
        }
        /* Orange glow override */
        .test-card .stat-card[data-stat="wnd"].orange-glow::after,
        .test-card .stat-card[data-stat="wnd_on_time"].orange-glow::after {
            background: radial-gradient(circle, rgba(255, 165, 0, 0.9) 0%, rgba(255, 140, 0, 0.6) 40%, transparent 70%) !important;
            opacity: 1;
        }
        /* Red glow override */
        .test-card .stat-card[data-stat="wnd"].red-glow::after,
        .test-card .stat-card[data-stat="wnd_on_time"].red-glow::after {
            background: radial-gradient(circle, rgba(239, 68, 68, 0.9) 0%, rgba(220, 38, 38, 0.6) 40%, transparent 70%) !important;
            opacity: 1;
        }
        .test-card h3 {
            color: #60a5fa;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .test-card .expected {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .test-card .value {
            color: #e2e8f0;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .legend {
            background: #1e293b;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #334155;
        }
        .legend h3 {
            color: #60a5fa;
            margin-bottom: 15px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background: #0f172a;
            border-radius: 8px;
        }
        .legend-color {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .legend-color.grey {
            background: radial-gradient(circle, rgba(55, 71, 79, 0.6) 0%, transparent 70%);
        }
        .legend-color.orange {
            background: radial-gradient(circle, rgba(255, 165, 0, 0.7) 0%, transparent 70%);
        }
        .legend-color.red {
            background: radial-gradient(circle, rgba(239, 68, 68, 0.7) 0%, transparent 70%);
        }
        .info-box {
            background: #1e293b;
            border-left: 4px solid #60a5fa;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        .info-box p {
            margin: 5px 0;
            color: #cbd5e1;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1><i class="fas fa-vial"></i> WND Glow Effect Test</h1>
            <p>Testing glow colors based on percentage values</p>
        </div>

        <div class="info-box">
            <h3 style="color: #60a5fa; margin-top: 0;">Glow Logic Rules:</h3>
            <p><strong>GREY (No Glow):</strong> Value > -10% (e.g., -5%, 0%, 10%)</p>
            <p><strong>ORANGE Glow:</strong> Value between -20.5% and -10.6% (inclusive)</p>
            <p><strong>RED Glow:</strong> Value ≤ -20.6% (e.g., -21%, -30%, -50%)</p>
        </div>

        <div class="legend">
            <h3><i class="fas fa-palette"></i> Glow Color Legend</h3>
            <div class="legend-item">
                <div class="legend-color grey"></div>
                <div>
                    <strong>GREY (Default)</strong> - No glow effect<br>
                    <small>For values > -10%</small>
                </div>
            </div>
            <div class="legend-item">
                <div class="legend-color orange"></div>
                <div>
                    <strong>ORANGE Glow</strong> - Moderate warning<br>
                    <small>For values between -20.5% and -10.6%</small>
                </div>
            </div>
            <div class="legend-item">
                <div class="legend-color red"></div>
                <div>
                    <strong>RED Glow</strong> - Critical warning<br>
                    <small>For values ≤ -20.6%</small>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-chart-line"></i> WND Stat-Card Tests</h2>
            <div class="test-grid">
                <!-- GREY (No Glow) Tests -->
                <div class="test-card">
                    <h3>Test 1: Positive Value</h3>
                    <div class="expected">Expected: GREY (No Glow)</div>
                    <div class="value">Value: 5%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="5" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">5%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Test 2: Zero Value</h3>
                    <div class="expected">Expected: GREY (No Glow)</div>
                    <div class="value">Value: 0%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="0" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">0%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Test 3: Slightly Negative</h3>
                    <div class="expected">Expected: GREY (No Glow)</div>
                    <div class="value">Value: -5%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-5" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-5%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Test 4: Boundary -10%</h3>
                    <div class="expected">Expected: GREY (No Glow)</div>
                    <div class="value">Value: -10%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-10" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-10%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <!-- ORANGE Glow Tests -->
                <div class="test-card">
                    <h3>Test 5: Lower Boundary ORANGE</h3>
                    <div class="expected">Expected: ORANGE Glow</div>
                    <div class="value">Value: -10.6%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-10.6" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-10.6%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Test 6: Middle ORANGE Range</h3>
                    <div class="expected">Expected: ORANGE Glow</div>
                    <div class="value">Value: -15%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-15" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-15%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Test 7: Upper Boundary ORANGE</h3>
                    <div class="expected">Expected: ORANGE Glow</div>
                    <div class="value">Value: -20.5%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-20.5" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-20.5%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <!-- RED Glow Tests -->
                <div class="test-card">
                    <h3>Test 8: Lower Boundary RED</h3>
                    <div class="expected">Expected: RED Glow</div>
                    <div class="value">Value: -20.6%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-20.6" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-20.6%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Test 9: Moderate RED</h3>
                    <div class="expected">Expected: RED Glow</div>
                    <div class="value">Value: -30%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-30" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-30%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Test 10: Extreme RED</h3>
                    <div class="expected">Expected: RED Glow</div>
                    <div class="value">Value: -50%</div>
                    <div class="stat-card" data-stat="wnd" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-50" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-50%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-hourglass-half"></i> WND On-Time Stat-Card Tests</h2>
            <div class="test-grid">
                <!-- GREY Tests -->
                <div class="test-card">
                    <h3>Test 11: Positive Value</h3>
                    <div class="expected">Expected: GREY (No Glow)</div>
                    <div class="value">Value: 8%</div>
                    <div class="stat-card" data-stat="wnd_on_time" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="8" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">8%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND on Time</div>
                        </div>
                    </div>
                </div>

                <!-- ORANGE Tests -->
                <div class="test-card">
                    <h3>Test 12: ORANGE Range</h3>
                    <div class="expected">Expected: ORANGE Glow</div>
                    <div class="value">Value: -18%</div>
                    <div class="stat-card" data-stat="wnd_on_time" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-18" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-18%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND on Time</div>
                        </div>
                    </div>
                </div>

                <!-- RED Tests -->
                <div class="test-card">
                    <h3>Test 13: RED Range</h3>
                    <div class="expected">Expected: RED Glow</div>
                    <div class="value">Value: -25%</div>
                    <div class="stat-card" data-stat="wnd_on_time" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                        <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value" data-target="-25" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">-25%</div>
                            <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND on Time</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-sliders-h"></i> Interactive Test</h2>
            <div class="test-card" style="max-width: 500px; margin: 0 auto;">
                <h3>Dynamic Value Test</h3>
                <p style="color: #94a3b8; margin-bottom: 15px;">Adjust the slider to see glow change in real-time:</p>
                <input type="range" id="valueSlider" min="-60" max="20" value="0" step="0.1" style="width: 100%; margin-bottom: 15px;">
                <div class="value" id="currentValue" style="text-align: center; font-size: 24px;">Value: 0%</div>
                <div class="expected" id="expectedGlow" style="text-align: center; margin-bottom: 20px;">Expected: GREY (No Glow)</div>
                <div class="stat-card" data-stat="wnd" id="dynamicCard" style="position: relative; width: 100%; min-height: 120px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                    <div class="stat-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value" id="dynamicValue" data-target="0" style="font-size: 28px; font-weight: bold; color: #e2e8f0;">0%</div>
                        <div class="stat-label" style="color: #94a3b8; margin-top: 5px;">WND</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to apply glow class based on WND value (same as in dashboards)
        function applyWndGlow(statType, value) {
            const card = document.querySelector(`.stat-card[data-stat="${statType}"]`);
            if (!card) return;
            
            // Remove existing glow classes
            card.classList.remove('orange-glow', 'red-glow');
            
            // Parse value to number
            const numValue = parseFloat(value);
            if (isNaN(numValue)) return;
            
            // Apply glow based on value
            // If value > -10%: No glow (default GREY) - good/acceptable values
            // If value is between -20.5% and -10.6% (inclusive): ORANGE glow - moderately bad
            // If value ≤ -20.6%: RED glow - very bad (takes priority over ORANGE)
            if (numValue <= -20.6) {
                card.classList.add('red-glow');
            } else if (numValue <= -10.6 && numValue >= -20.5) {
                card.classList.add('orange-glow');
            }
            // Otherwise (value > -10%), no glow class (default GREY)
        }

        // Apply glow to all test cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Get all WND and WND_On_Time stat cards
            const wndCards = document.querySelectorAll('.stat-card[data-stat="wnd"]');
            const wndOnTimeCards = document.querySelectorAll('.stat-card[data-stat="wnd_on_time"]');
            
            // Apply glow to WND cards
            wndCards.forEach(card => {
                const valueElement = card.querySelector('.stat-value');
                if (valueElement) {
                    const value = parseFloat(valueElement.getAttribute('data-target') || valueElement.textContent.replace('%', ''));
                    if (!isNaN(value)) {
                        applyWndGlow('wnd', value);
                    }
                }
            });
            
            // Apply glow to WND_On_Time cards
            wndOnTimeCards.forEach(card => {
                const valueElement = card.querySelector('.stat-value');
                if (valueElement) {
                    const value = parseFloat(valueElement.getAttribute('data-target') || valueElement.textContent.replace('%', ''));
                    if (!isNaN(value)) {
                        applyWndGlow('wnd_on_time', value);
                    }
                }
            });

            // Interactive slider
            const slider = document.getElementById('valueSlider');
            const currentValueEl = document.getElementById('currentValue');
            const expectedGlowEl = document.getElementById('expectedGlow');
            const dynamicValueEl = document.getElementById('dynamicValue');
            const dynamicCard = document.getElementById('dynamicCard');
            
            function updateDynamicCard() {
                const value = parseFloat(slider.value);
                currentValueEl.textContent = `Value: ${value.toFixed(1)}%`;
                dynamicValueEl.textContent = value.toFixed(1) + '%';
                dynamicValueEl.setAttribute('data-target', value);
                
                // Update expected glow text
                let expectedText = '';
                if (value > -10) {
                    expectedText = 'Expected: GREY (No Glow)';
                } else if (value >= -20.5 && value <= -10.6) {
                    expectedText = 'Expected: ORANGE Glow';
                } else if (value <= -20.6) {
                    expectedText = 'Expected: RED Glow';
                }
                expectedGlowEl.textContent = expectedText;
                
                // Apply glow
                dynamicCard.classList.remove('orange-glow', 'red-glow');
                if (value <= -20.6) {
                    dynamicCard.classList.add('red-glow');
                } else if (value <= -10.6 && value >= -20.5) {
                    dynamicCard.classList.add('orange-glow');
                }
            }
            
            slider.addEventListener('input', updateDynamicCard);
            updateDynamicCard(); // Initial update
        });
    </script>
</body>
</html>

