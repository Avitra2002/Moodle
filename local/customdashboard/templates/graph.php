<div class="graph-row">
    <div class="graph">
        <canvas id="enrollmentChart"></canvas>
    </div>
    <div class="graph">
        <canvas id="statusChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const enrolled = <?= json_encode($enrolled_data) ?>;
const completed = <?= json_encode($completed_data) ?>;
const inProgress = <?= json_encode($inprogress_data) ?>;

new Chart(document.getElementById('enrollmentChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Enrolled Students',
            data: enrolled,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: { title: { display: true, text: 'Enrollment per Course' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Completed',
                data: completed,
                backgroundColor: 'rgba(75, 192, 192, 0.6)'
            },
            {
                label: 'In Progress',
                data: inProgress,
                backgroundColor: 'rgba(255, 205, 86, 0.6)'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { title: { display: true, text: 'Completion Status per Course' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>