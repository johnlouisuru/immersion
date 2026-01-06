<?php 

            // This is to check if account is Division or Center?
            $max_of_10_display = 0; //Max of 10 Display.
              $query_arriving = "SELECT * FROM vessel_logs WHERE npoc_cid IS NOT NULL AND npoc_did IS NOT NULL ORDER BY tdoa DESC";
              $query_returned = secure_query_no_params($pdo, $query_arriving);
              $total_fetched_arriving = $query_returned->rowCount();
          ?>
          <div class="card">
            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
              <div class="bg-gradient-dark shadow-dark border-radius-lg pt-2 pb-1">
                <h6 class="text-white text-uppercase ps-3">Arrival Vessel [<?=$total_fetched_arriving?>]</h6>
              </div>
            </div>
            <div class="card-body">

                        <!-- Start of PSCC Details -->
                  <div class="table-responsive">
                    <table id="example" class="table datatable table-striped text-sm">
                      <thead>
                        <tr>
                          <th>Name & Type of Vessel</th>
                          <th>Last Port of Call</th>
                          <th>ATD</th>
                          <th>Next Port of Call</th>
                          <th>ETA</th>
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
                              $fetch_tov = get_type_of_vessel($pdo, $tov);
                                if($fetch_tov){
                                  foreach($fetch_tov as $row){
                                    $vname = $row['name'];
                                    $vimo = $row['imo'];
                                    $tov_name_display = $row['tov'];
                                  }
                                }                  
                            ?>
                            <tr>
                              <td><a class='link-info' href="vprofile?id=<?=md5($arrived['vid'])?>"><?=strtoupper($vname)?>[<?=$tov_name_display?>]</a></td>
                              <td><?=get_last_port_of_call($pdo, $arrived['did'])?></td>
                              <td><?=date("d M Y H:i", strtotime($arrived['tdod']))?>H</td>
                              <td><?=get_last_port_of_call($pdo, $arrived['npoc_did'])?></td>
                              <td><?=date("d M Y H:i", strtotime($arrived['npoc_edta']))?>H</td>
                               </tr>
                        <?php
                              }
                            }
                            else { ?>
                              <tr>
                                  <td colspan="5"><center><b>No Entries Found</b></center></td>
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