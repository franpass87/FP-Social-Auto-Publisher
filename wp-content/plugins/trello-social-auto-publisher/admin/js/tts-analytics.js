(function(){
    if ( typeof ttsAnalytics === 'undefined' || ! window.Chart ) {
        return;
    }

    const ctx = document.getElementById('tts-analytics-chart');
    if ( ! ctx ) {
        return;
    }

    const raw = ttsAnalytics.data || {};
    const labels = Object.keys(raw);
    const channelSet = new Set();
    labels.forEach(date => {
        const obj = raw[date];
        Object.keys(obj).forEach(ch => channelSet.add(ch));
    });
    const channels = Array.from(channelSet);
    const datasets = channels.map(ch => {
        return {
            label: ch,
            data: labels.map(date => raw[date][ch] ? raw[date][ch] : 0),
            fill: false,
            borderWidth: 2
        };
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
})();
