/*This file is part of NextGEN Gallery Media Library Addon.NextGEN Gallery Media Library Addon is free software: you can redistribute it and/or modifyit under the terms of the GNU General Public License as published bythe Free Software Foundation, either version 3 of the License, or(at your option) any later version.NextGEN Gallery Media Library Addon is distributed in the hope that it will be useful,but WITHOUT ANY WARRANTY; without even the implied warranty ofMERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See theGNU General Public License for more details.You should have received a copy of the GNU General Public Licensealong with Foobar.  If not, see <http://www.gnu.org/licenses/>.*/<?php
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}
?>
<div class="wrap">
    <div class="icon32" id="icon-nextgen-gallery"></div>
    <h2>NextGEN Gallery Media Library Addon</h2>
</div>