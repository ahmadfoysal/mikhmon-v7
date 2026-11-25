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

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  if (isset($_POST["submit"])) {
    // the logo is stored in the data folder of this customer as logo-<session>.png
    $galat = '<div class="box bg-danger"></i> Alert!<br> File was not uploaded. Please choose a PNG image smaller than 2 MB.</div>';
    $mkltmp = isset($_FILES["UploadLogo"]["tmp_name"]) ? $_FILES["UploadLogo"]["tmp_name"] : "";
    if ($mkltmp != "" && is_uploaded_file($mkltmp) && $_FILES["UploadLogo"]["size"] <= 2000000 && mkl_routers() && mkl_router($session)['exists']) {
      $check = getimagesize($mkltmp);
      if ($check !== false && $check[2] == IMAGETYPE_PNG) {
        if (move_uploaded_file($mkltmp, mkl_logo_file($session))) {
          $galat = '<div class="box bg-success"></i> Success!<br> The logo has been uploaded.</div>';
        }
      } else {
        $galat = '<div class="box bg-danger"></i> Alert!<br> Only PNG images are allowed.</div>';
      }
    }
//echo "<script>window.location='./admin.php?id=uplogo&session=".$session."'</script>";
  }
}
?>
<div class="row">
<div class="col-12">
  <div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-upload"></i> <?= $_upload_logo ?></h3>
    </div>
    <div class="card-body">
      <div>
    <?= $galat; ?>
      <form action="" method="post" enctype="multipart/form-data">

          <div class="pd-10">PNG, max 2 MB (<?= htmlspecialchars($session); ?>)</div>
          <div class="input-group">
            <div class="input-group-4 col-box-8">
                <input style="cursor: pointer; " type="file" class="group-item group-item-l" name="UploadLogo" >
            </div>
            <div class="input-group-2 col-box-4">
                <input style="cursor: pointer; font-size: 14px; padding:8px;" class="group-item group-item-r" type="submit" value="<?= $_upload ?>" title="Upload logo" name="submit">
            </div>

      </form>
    </div>
      <div class="mr-t-10">
      <table class="table table-bordered table-hover">
        <thead>
        <tr>
          <th><?= $_list_logo ?></th>
          <th><?= $_action ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach (mkl_routers() as $mklname => $mklr) {
          if (is_file(mkl_logo_file($mklname))) {
            $file = "logo-" . $mklname . ".png";
            $mklurl = mkl_logo_url($mklname); ?>
              <tr>
                <td><a href="javascript:window.open('<?= $mklurl; ?>','_blank','width=300,height=300')"><img height="30px" src="<?= $mklurl; ?>" title="Open <?= $file; ?>"></a><br><span><?= $file; ?></span></td>
                <td><a class="btn bg-danger" href="javascript:void(0)" onclick="if(confirm('Sure to delete <?= $file; ?> ?')){window.location='./admin.php?id=remove-logo&logo=<?= $file; ?>&session=<?= $session ?>'}else{}"><i class="fa fa-trash"></i></a>
                </td>
              </tr>
<?php
          }
        } ?>
      </tbody>
    </table>
  </div>
  
  </div>
</div>
</div>
</div>