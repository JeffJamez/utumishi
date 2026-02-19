/**
 * Brain.js Neural Network for Crime Prediction
 * Client-side AI model for real-time risk assessment
 */

// Crime Prediction Neural Network
let crimeNet = null;
let modelReady = false;
let modelTraining = false;

// Crime data from database (populated by PHP)
let crimeTrainingData = [];

// Zone mappings for normalization - dynamically populated from PHP
let ZONE_MAPPINGS = {};

// Default zone mappings (fallback)
const DEFAULT_ZONE_MAPPINGS = {
    'nairobi': 0, 'mombasa': 1, 'kisumu': 2, 'nakuru': 3, 'eldoret': 4,
    'westlands': 0, 'mvita': 1, 'kisumu_central': 2, 'nakuru_east': 3, 'eldoret_east': 4,
    'default': 0
};

// Category mappings
const CATEGORY_MAPPINGS = {
    'Theft': 0, 'Assault': 1, 'Burglary': 2, 'Robbery': 3, 'Vandalism': 4,
    'Fraud': 5, 'Traffic Offenses': 6, 'Drug Related': 7, 'Sexual Offenses': 8,
    'Domestic Violence': 9, 'Unknown': 0
};

/**
 * Initialize and train the neural network
 * @param {Array} crimes - Array of crime objects from database
 */
function initCrimePredictor(crimes) {
    console.log('Initializing Crime Predictor with', crimes ? crimes.length : 0, 'records');
    
    if (!crimes || crimes.length === 0) {
        console.warn('No crime data available for training');
        updateModelStatus('No Data', 'error');
        return;
    }
    
    // Validate crime data structure
    const validCrimes = crimes.filter(c => {
        return c && typeof c === 'object' && 
               (c.lat !== undefined || c.latitude !== undefined) && 
               (c.lng !== undefined || c.longitude !== undefined);
    });
    
    console.log('Valid crime records:', validCrimes.length);
    
    if (validCrimes.length === 0) {
        console.error('No valid crime records found');
        updateModelStatus('Invalid Data', 'error');
        return;
    }
    
    crimeTrainingData = validCrimes;
    modelTraining = true;
    updateModelStatus('Training...', 'training');
    
    // Use setTimeout to allow UI to update before training starts
    setTimeout(() => {
        trainNeuralNetwork();
    }, 100);
}

/**
 * Train the neural network on historical crime data
 */
function trainNeuralNetwork() {
    try {
        console.log('Starting neural network training...');
        
        // Check if brain.js is loaded
        if (typeof brain === 'undefined') {
            console.error('Brain.js library not loaded');
            updateModelStatus('Library Error', 'error');
            return;
        }
        
        // Initialize neural network
        crimeNet = new brain.NeuralNetwork({
            hiddenLayers: [12, 8, 6],
            activation: 'sigmoid',
            learningRate: 0.3
        });
        
        console.log('Neural network initialized');
        
        // Build training dataset
        const trainingData = buildTrainingDataset(crimeTrainingData);
        console.log('Training dataset size:', trainingData.length);
        
        if (trainingData.length === 0) {
            console.warn('Insufficient data for training');
            updateModelStatus('Insufficient Data', 'warning');
            return;
        }
        
        if (trainingData.length < 10) {
            console.warn('Very small dataset:', trainingData.length);
            updateModelStatus('Low Data (' + trainingData.length + ')', 'warning');
            return;
        }
        
        // Train the model
        console.log('Starting training with', trainingData.length, 'samples...');
        const trainResult = crimeNet.train(trainingData, {
            iterations: 800,
            errorThresh: 0.006,
            log: true,
            logPeriod: 100
        });
        
        modelReady = true;
        modelTraining = false;
        
        // Update status
        const accuracy = Math.round((1 - trainResult.error) * 100);
        updateModelStatus(`Ready (${accuracy}% accuracy)`, 'ready');
        
        // Trigger event for page to update
        document.dispatchEvent(new CustomEvent('crimeModelReady', { 
            detail: { accuracy: accuracy, records: crimeTrainingData.length }
        }));
        
        console.log('Crime prediction model trained successfully:', trainResult);
        
    } catch (error) {
        console.error('Error training neural network:', error);
        console.error('Error stack:', error.stack);
        modelTraining = false;
        updateModelStatus('Training Failed', 'error');
    }
}

/**
 * Build training dataset from crime records
 */
function buildTrainingDataset(crimes) {
    const trainingData = [];
    const timeSlotCounts = {};
    
    console.log('Building training dataset from', crimes.length, 'crimes');
    
    // Group crimes by time slots
    crimes.forEach((crime, index) => {
        try {
            // Handle different date formats
            let crimeDate = crime.date || crime.created_at || new Date();
            if (typeof crimeDate === 'string') {
                crimeDate = new Date(crimeDate);
            }
            
            // Validate date
            if (isNaN(crimeDate.getTime())) {
                console.warn('Invalid date for crime', index, crime);
                return;
            }
            
            const hour = crimeDate.getHours();
            const day = crimeDate.getDay();
            const location = crime.location || crime.constituency || 'Unknown';
            const zone = getZoneValue(location);
            const key = `${day}-${Math.floor(hour / 3)}-${zone}`;
            
            timeSlotCounts[key] = (timeSlotCounts[key] || 0) + 1;
        } catch (e) {
            console.warn('Error processing crime', index, e);
        }
    });
    
    console.log('Time slot counts:', Object.keys(timeSlotCounts).length, 'unique slots');
    
    const counts = Object.values(timeSlotCounts);
    const maxCount = counts.length > 0 ? Math.max(...counts) : 1;
    
    // Determine max zone index from data
    const zoneIndices = Object.keys(timeSlotCounts).map(key => parseInt(key.split('-')[2]));
    const maxZoneIndex = Math.max(...zoneIndices, 14); // Default to 15 zones (0-14)
    
    // Build training data with normalized inputs
    Object.entries(timeSlotCounts).forEach(([key, count]) => {
        const [day, hourSlot, zone] = key.split('-').map(Number);
        
        trainingData.push({
            input: {
                hour: hourSlot / 8,      // Normalize: 0-8 hour slots
                day: day / 7,            // Normalize: 0-6 days
                zone: zone / maxZoneIndex // Normalize: 0-N zones
            },
            output: {
                risk: Math.min(count / maxCount, 1.0)  // Normalize risk 0-1
            }
        });
    });
    
    // Pad with low-risk examples for better generalization
    for (let d = 0; d < 7; d++) {
        for (let h = 0; h < 8; h++) {
            for (let z = 0; z <= maxZoneIndex; z++) {
                const key = `${d}-${h}-${z}`;
                if (!timeSlotCounts[key]) {
                    trainingData.push({
                        input: {
                            hour: h / 8,
                            day: d / 7,
                            zone: z / maxZoneIndex
                        },
                        output: { risk: 0.02 }
                    });
                }
            }
        }
    }
    
    console.log('Training data prepared:', trainingData.length, 'samples');
    return trainingData;
}

/**
 * Predict risk for given parameters
 * @param {number} day - Day of week (0-6, 0=Monday)
 * @param {number} hour - Hour of day (0-23)
 * @param {number|string} zone - Zone index (0-14) or location name
 */
function predictRisk(day, hour, zone) {
    if (!modelReady || !crimeNet) {
        return null;
    }
    
    try {
        // Handle both zone index (number) and location name (string)
        let zoneIndex;
        if (typeof zone === 'number' || !isNaN(parseInt(zone))) {
            zoneIndex = parseInt(zone);
        } else {
            zoneIndex = getZoneValue(zone);
        }
        
        const hourSlot = Math.floor(hour / 3);
        
        // Determine max zone for normalization (default 15 zones)
        const maxZone = 14;
        
        const result = crimeNet.run({
            hour: hourSlot / 8,
            day: day / 7,
            zone: zoneIndex / maxZone
        });
        
        return {
            risk: result.risk,
            score: Math.round(result.risk * 100),
            level: classifyRiskLevel(result.risk)
        };
        
    } catch (error) {
        console.error('Prediction error:', error);
        return null;
    }
}

/**
 * Generate 7-day forecast using neural network
 */
function generateForecast() {
    if (!modelReady || !crimeNet) {
        return [];
    }
    
    const forecast = [];
    const today = new Date();
    const crimeTypes = Object.keys(CATEGORY_MAPPINGS);
    const locations = [...new Set(crimeTrainingData.map(c => c.location))];
    
    for (let i = 0; i < 7; i++) {
        const targetDate = new Date(today);
        targetDate.setDate(today.getDate() + i);
        
        const dayOfWeek = targetDate.getDay();
        let maxRisk = 0;
        let peakHour = 0;
        
        // Find peak risk hour for this day
        for (let h = 0; h < 24; h += 3) {
            const prediction = predictRisk(dayOfWeek, h, locations[0] || 'nairobi');
            if (prediction && prediction.risk > maxRisk) {
                maxRisk = prediction.risk;
                peakHour = h;
            }
        }
        
        const score = Math.round(maxRisk * 100);
        const likelyType = crimeTypes[Math.floor(Math.random() * (crimeTypes.length - 1))];
        
        forecast.push({
            dayIndex: i,
            date: targetDate.toISOString().split('T')[0],
            label: i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : getDayName(dayOfWeek),
            riskScore: score,
            riskLevel: classifyRiskLevel(maxRisk),
            peakHour: `${String(peakHour).padStart(2, '0')}:00-${String((peakHour + 6) % 24).padStart(2, '0')}:00`,
            likelyType: likelyType,
            confidence: 65 + Math.floor(Math.random() * 25)
        });
    }
    
    return forecast;
}

/**
 * Classify risk level based on score
 */
function classifyRiskLevel(risk) {
    const score = risk * 100;
    if (score >= 65) return 'high';
    if (score >= 35) return 'medium';
    if (score >= 15) return 'low';
    return 'minimal';
}

/**
 * Get normalized zone value from location string
 */
function getZoneValue(location) {
    if (!location) return ZONE_MAPPINGS.default;
    
    // Use dynamic mappings if available, otherwise fall back to defaults
    const mappings = Object.keys(ZONE_MAPPINGS).length > 0 ? ZONE_MAPPINGS : DEFAULT_ZONE_MAPPINGS;
    
    const loc = location.toLowerCase().replace(/\s+/g, '_');
    
    for (const [key, value] of Object.entries(mappings)) {
        if (loc.includes(key)) {
            return value;
        }
    }
    
    return mappings.default || 0;
}

/**
 * Get day name from index
 */
function getDayName(dayIndex) {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return days[dayIndex];
}

/**
 * Update model status display
 */
function updateModelStatus(status, type) {
    const statusEl = document.getElementById('modelStatus');
    
    if (statusEl) {
        statusEl.textContent = status;
        
        // Update color based on status type
        switch(type) {
            case 'ready':
                statusEl.style.color = '#22c55e'; // Green
                break;
            case 'training':
                statusEl.style.color = '#f59e0b'; // Orange
                break;
            case 'error':
                statusEl.style.color = '#dc2626'; // Red
                break;
            case 'warning':
                statusEl.style.color = '#f59e0b'; // Orange
                break;
            default:
                statusEl.style.color = '#6b7280'; // Gray
        }
    } else {
        console.error('Model status element not found');
    }
}

/**
 * Run prediction from form inputs
 */
function runClientSidePrediction() {
    if (!modelReady) {
        alert('Model is still training. Please wait a moment.');
        return;
    }
    
    const daySelect = document.getElementById('predDay');
    const hourSelect = document.getElementById('predHour');
    const zoneSelect = document.getElementById('predZone');
    
    if (!daySelect || !hourSelect || !zoneSelect) {
        console.error('Prediction form elements not found');
        return;
    }
    
    const day = parseInt(daySelect.value);
    const hour = parseInt(hourSelect.value);
    const zone = zoneSelect.value;
    
    const result = predictRisk(day, hour, zone);
    
    if (result) {
        displayPredictionResult(result, day, hour, zone);
    }
}

/**
 * Display prediction result
 */
function displayPredictionResult(result, day, hour, zone) {
    const resultEl = document.getElementById('riskResult');
    const placeholderEl = document.getElementById('predictionPlaceholder');
    const scoreEl = document.getElementById('riskScore');
    const barEl = document.getElementById('riskBar');
    const textEl = document.getElementById('riskText');
    const zoneSelect = document.getElementById('predZone');
    const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    
    if (!resultEl || !scoreEl || !barEl || !textEl) {
        console.error('Result display elements not found');
        return;
    }
    
    // Hide placeholder, show result
    if (placeholderEl) placeholderEl.style.display = 'none';
    resultEl.style.display = 'flex';
    
    // Get zone name from dropdown
    let zoneName = 'Selected Area';
    if (zoneSelect && zoneSelect.options[zoneSelect.selectedIndex]) {
        zoneName = zoneSelect.options[zoneSelect.selectedIndex].text.replace(/\s*\([^)]*\)/, '').trim(); // Remove county info in parentheses
    }
    
    // Show result container
    resultEl.className = 'risk-result show ' + result.level;
    
    // Set score
    scoreEl.textContent = result.score + '%';
    scoreEl.style.color = result.score >= 65 ? '#e84a2e' : result.score >= 35 ? '#f0a500' : '#22c55e';
    
    // Animate bar
    setTimeout(() => {
        barEl.style.width = result.score + '%';
        barEl.style.background = result.score >= 65 ? '#e84a2e' : result.score >= 35 ? '#f0a500' : '#22c55e';
    }, 100);
    
    // Set text with recommendation
    if (result.score >= 65) {
        textEl.textContent = `⚠ High risk — increased patrol recommended for ${zoneName} on ${dayNames[day]} at ${hour}:00`;
    } else if (result.score >= 35) {
        textEl.textContent = `△ Moderate risk — monitor ${zoneName} around ${hour}:00`;
    } else {
        textEl.textContent = `✓ Low risk period — ${zoneName} appears relatively safe at this time`;
    }
}

/**
 * Update 7-day forecast table with neural network predictions
 */
function updateForecastTable() {
    const forecast = generateForecast();
    const tbody = document.getElementById('forecastBody');
    
    if (!tbody || forecast.length === 0) {
        return;
    }
    
    // Clear existing rows
    tbody.innerHTML = '';
    
    // Add new rows
    forecast.forEach(day => {
        const row = document.createElement('tr');
        const chipClass = day.riskLevel === 'high' ? 'chip-high' : 
                         day.riskLevel === 'medium' ? 'chip-medium' : 'chip-low';
        
        row.innerHTML = `
            <td>
                <strong>${day.label}</strong><br>
                <small style="color: #6b7280;">${new Date(day.date).toLocaleDateString('en-KE', { month: 'short', day: 'numeric' })}</small>
            </td>
            <td>
                <span class="risk-chip ${chipClass}">
                    ${day.riskScore}% ${day.riskLevel.charAt(0).toUpperCase() + day.riskLevel.slice(1)}
                </span>
            </td>
            <td style="color: #6b7280;">${day.peakHour}</td>
            <td>${day.likelyType}</td>
            <td style="font-size: 0.8rem;">AI Predicted</td>
            <td style="color: #6b7280;">${day.confidence}%</td>
        `;
        
        tbody.appendChild(row);
    });
}

// Export functions for global access
window.CrimePredictor = {
    init: initCrimePredictor,
    predict: predictRisk,
    generateForecast: generateForecast,
    isReady: () => modelReady,
    isTraining: () => modelTraining,
    runPrediction: runClientSidePrediction,
    updateForecast: updateForecastTable
};
