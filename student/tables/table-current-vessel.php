<?php 
            // This is to check if account is Division or Center?
            $max_of_10_display = 0; //Max of 10 Display.
              $query_arriving = "SELECT * FROM vessel_logs WHERE tdoa IS NOT NULL AND tdod IS NULL ORDER BY tdoa DESC";
              $query_returned = secure_query_no_params($pdo, $query_arriving);
              $total_fetched_arriving = $query_returned->rowCount();
          ?>
          <div class="card">
            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
              <div class="bg-gradient-dark shadow-dark border-radius-lg pt-2 pb-1">
                <h6 class="text-white text-uppercase ps-3">Current Vessel abd PCG Ports [<?=$total_fetched_arriving?>]</h6>
              </div>
            </div>
            <div class="card-body">
                        <!-- Start of PSCC Details -->
                  <div class="table-responsive">
                    <table id="example" class="table datatable table-striped text-sm">
                      <thead>
                        <tr>
                          <th>Name & Type of Vessel</th>
                          <th>Current Location</th>
                           <th>ATA</th>
                          <th>Last Port of Call</th>
                         
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        
                                  if($query_returned){ 
                                    while($arrived = $query_returned->fetch()){
                                      if($max_of_10_display == 10){
                                        exit();
                                      } 
                                      $max_of_10_display++;
                                      $tov = "SELECT a.name, a.imo, b.tov 
                                              FROM vessel_details a 
                                              INNER JOIN vessel b 
                                              ON a.tov = b.id
                                              WHERE a.id=".$arrived['vid']."";
                                      $fetch_tov = secure_query_no_params($pdo, $tov);
                                        if($fetch_tov){
                                          while($display_tov = $fetch_tov->fetch()){
                                            $vname = $display_tov['name'];
                                            $vimo = $display_tov['imo'];
                                            $tov_name_display = $display_tov['tov'];
                                          }
                                        }
                                      //Getting Last Port of Call
                                      if($arrived['lpoc'] != NULL){
                                          $station_lpoc = $arrived['lpoc'];
                                      } else {
                                        $station_lpoc = get_last_port_of_call($pdo, $arrived['lpoc_did']);
                                      }
                                                       
                            ?>
                            <tr>
                              <td><a class='link-info' href="vprofile?id=<?=md5($arrived['vid'])?>"><?=$vname?>[<?=$tov_name_display?>]</a></td>
                              <td><?=$arrived['status']?></td>
                              <td><?=date("d M Y H:i", strtotime($arrived['tdoa']))?>H</td>
                              <td><?=$station_lpoc?></td>
                              
                               </tr>
                        <?php
                              }
                            }
                            else { ?>
                              <tr>
                                  <td colspan="4"><center><b>No Entries Found</b></center></td>
                              </tr>
                            <?php  
                            }
                        ?>
                      </tbody>
                  </table>
                </div>
                          <hr class="dark horizontal">
              <div class="d-flex ">
                <span class="material-symbols-rounded text-sm my-auto me-1">content_paste_search</span>
                <a href='#' class="mb-0 text-sm link-info">Click this to See Full Details of [<?=$total_fetched_arriving?> records]</a>
              </div>

            </div>
          </div>