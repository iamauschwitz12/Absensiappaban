<?php
// File testing sederhana
echo "<h1>Testing Simple POST</h1>";

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<p>Method: POST</p>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    if(isset($_POST['nis'])) {
        echo "<p>NIS diterima: " . $_POST['nis'] . "</p>";
    }
} else {
    echo '<form method="POST">
        <input type="text" name="nis" value="20250012">
        <button type="submit">Test POST</button>
    </form>';
}
?>