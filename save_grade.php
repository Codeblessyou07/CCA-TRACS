  <?php
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "cca_tracs";
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $student = $_POST['student_name'];
    $subj = $_POST['subject'];
    $mid = $_POST['midterm'];
    $fin = $_POST['final'];
    $avg = ($mid + $fin) / 2;
    if(avg >= 75) {
        $rem = "Passed";
    } else {
        $rem = "Failed";
    }
    $sql = "INSERT INTO grades (student_name, subject, midterm, final, average, remarks) VALUES ('$student', '$subj', '$mid', '$fin', '$avg', '$rem')";
    if ($conn->query($sql) === TRUE) {
        header ("Location: prof_dashboard.php");
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
    $conn->close();
?>