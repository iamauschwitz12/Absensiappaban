<?php
// Kita akan buat password "admin" dan "guru"
$pass_admin = password_hash("admin", PASSWORD_DEFAULT);
$pass_guru  = password_hash("guru", PASSWORD_DEFAULT);

echo "<h3>Copy kode di bawah ini ke Database:</h3>";
echo "<b>Hash untuk admin:</b><br>" . $pass_admin . "<br><br>";
echo "<b>Hash untuk guru:</b><br>" . $pass_guru;
?>