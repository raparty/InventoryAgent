<?php
// footer.php
?>
    <!-- jQuery / Bootstrap / DataTables -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    // ---------------------------
    //  DEVICE TYPE DISTRIBUTION
    // ---------------------------
    new Chart(document.getElementById('chartDeviceType'), {
        type: 'pie',
        data: {
            labels: ['Server', 'Desktop', 'Laptop'],
            datasets: [{
                data: [<?= $servers ?>, <?= $desktops ?>, <?= $laptops ?>],
                backgroundColor: ['#3b82f6', '#f06292', '#ffa726'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    // ---------------------------
    //  OS DISTRIBUTION
    // ---------------------------
    new Chart(document.getElementById('chartOS'), {
        type: 'doughnut',
        data: {
            labels: ['Windows 10', 'Windows 11'],
            datasets: [{
                data: [<?= $win10 ?>, <?= $win11 ?>],
                backgroundColor: ['#4e79a7', '#f28e2b'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    // ---------------------------
    //  LOCATION SHARE PIE
    // ---------------------------
    new Chart(document.getElementById('chartLocationShare'), {
        type: 'pie',
        data: {
            labels: ['HYDW', 'HYDE', 'UNKNOWN'],
            datasets: [{
                data: [<?= $locHYDW ?>, <?= $locHYDE ?>, <?= $locUNK ?>],
                backgroundColor: ['#66bb6a', '#42a5f5', '#9e9e9e'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    // ---------------------------
    //  PATCH COMPLIANCE BREAKDOWN (DONUT)
    // ---------------------------
    new Chart(document.getElementById('chartPatchPie'), {
        type: 'doughnut',
        data: {
            labels: ['Up-to-date', 'Outdated', 'No Data'],
            datasets: [{
                data: [<?= $up_to_date ?>, <?= $outdated_total ?>, <?= $not_responding ?>],
                backgroundColor: ['#3bb77e','#f6a623','#9aa0a6'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // ---------------------------
    //  PATCH STATUS BAR
    // ---------------------------
    new Chart(document.getElementById('chartPatchBar'), {
        type: 'bar',
        data: {
            labels: ['Up-to-date', 'Outdated', 'No Data'],
            datasets: [{
                label: 'Systems',
                data: [<?= $up_to_date ?>, <?= $outdated_total ?>, <?= $not_responding ?>],
                backgroundColor: ['#3bb77e','#f6a623','#9aa0a6'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 50 } }
            }
        }
    });

    // ---------------------------
    //  DATATABLES INIT (if used)
    // ---------------------------
    $(document).ready(function(){
        $('.datatable').DataTable({
            pageLength: 10
        });
    });
    </script>

  </body>
</html>
