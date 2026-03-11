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
