<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once(__DIR__ . '/../include/bootstrap.php');
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
}
?>
<style>
.iFWrapper {
	position: relative;
	padding-bottom: 56.25%; /* 16:9 */
	padding-top: 25px;
	height: 0;
}
.iFWrapper iframe {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
  height: 100%;
  border :none;
}
</style>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-info-circle"></i> About</h3>
      </div>
      <div class="card-body">
        <h3><?= htmlspecialchars(mkl_site('brand')) ?> <?= $_SESSION['v']; ?></h3>
<p>
  Hotspot voucher management for MikroTik RouterOS v6 and v7 (all date formats).
</p>
<p>
  <ul>
    <li>
      Based on MIKHMON v3 by Laksamadi Guko : <a href="https://laksa19.github.io">laksa19.github.io</a>
    </li>
    <li>
      Licence : <a href="https://www.gnu.org/licenses/old-licenses/gpl-2.0.html">GPLv2</a>
    </li>
    <li>
      API Class : <a href="https://github.com/BenMenking/routeros-api">routeros-api</a>
    </li>
  </ul>
</p>
<div>
    <i>MIKHMON Copyright &copy; 2018 Laksamadi Guko</i>
</div>
</div>
</div>
</div>
</div>
