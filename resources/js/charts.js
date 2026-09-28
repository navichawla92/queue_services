/* Report charts (Chart.js), registered as Alpine components on report pages. */
import {
    Chart, BarController, BarElement, LineController, LineElement, PointElement,
    CategoryScale, LinearScale, Legend, Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Legend, Tooltip);

const register = () => {
    window.Alpine.data('trendChart', (rows) => ({
        rows,
        chart: null,
        init() { this.draw(); },
        draw() {
            this.chart?.destroy();
            this.chart = new Chart(this.$refs.canvas, {
                data: {
                    labels: this.rows.map((r) => r.period),
                    datasets: [
                        { type: 'bar', label: 'Check-ins', data: this.rows.map((r) => r.tickets), backgroundColor: '#93c5fd', yAxisID: 'y' },
                        { type: 'bar', label: 'Served', data: this.rows.map((r) => r.served), backgroundColor: '#2563eb', yAxisID: 'y' },
                        { type: 'line', label: 'Avg wait (min)', data: this.rows.map((r) => r.avg_wait_min), borderColor: '#f59e0b', yAxisID: 'y1' },
                        { type: 'line', label: 'Avg service (min)', data: this.rows.map((r) => r.avg_service_min), borderColor: '#16a34a', yAxisID: 'y1' },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    animation: false,
                    scales: {
                        y: { beginAtZero: true, position: 'left' },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } },
                    },
                },
            });
        },
    }));
};

// Livewire loads Alpine; register before it starts (or immediately if already started).
if (window.Alpine) register();
else document.addEventListener('alpine:init', register);
