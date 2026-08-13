<div class="tqa-stack">
    <div>
        <div class="tqa-card">
            <div class="tqa-card__body">
                <h1 class="tqa-pagehead__title"><?php echo get_phrase('instructors_pending_blog'); ?>
                </h1>
            </div> <!-- end card body-->
        </div> <!-- end card -->
    </div><!-- end col-->
</div>

<div class="tqa-stack">
    <div>
        <div class="tqa-card">
            <div class="tqa-card__body">
                <h4 class="mb-3 header-title"><?php echo get_phrase('total_pending'); ?> <?php echo $pending_blogs->num_rows(); ?> <?php echo get_phrase('blogs'); ?></h1>
                <div class="tqa-table__wrap">
                    <table id="basic-datatable" class="tqa-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php echo get_phrase('creator'); ?></th>
                                <th><?php echo get_phrase('title'); ?></th>
                                <th><?php echo get_phrase('category'); ?></th>
                                <th><?php echo get_phrase('status'); ?></th>
                                <th><?php echo get_phrase('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($pending_blogs->result_array() as $key => $blog) : ?>
                            	<?php $user_details = $this->user_model->get_all_user($blog['user_id'])->row_array(); ?>
                                <tr>
                                    <td><?php echo $key + 1; ?></td>
                                    <td>
                                    	<a href="<?php echo site_url('home/instructor_page/'.$blog['user_id']); ?>" target="_blank">
	                                    	<div class="d-flex">
	                                    		<div>
	                                        		<img src="<?php echo $this->user_model->get_user_image_url($user_details['id']); ?>" alt="" height="50" width="50" class="img-fluid rounded-circle img-thumbnail">
	                                        	</div>
		                                        <div class="pl-1 pt-1">
			                                    	<?php echo $user_details['first_name'] . ' ' . $user_details['last_name']; ?>
			                                    	<p><?php echo $user_details['email']; ?></p>
			                                    </div>
			                                </div>
			                            </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo site_url('blog/details/'.slugify($blog['title']).'/'.$blog['blog_id']); ?>" target="_blank"><?php echo $blog['title']; ?></a><br>
                                        <small class="tqa-field__hint"><?php echo date('d M Y', $blog['added_date']); ?></small>
                                    </td>
                                    <td><?php echo $this->crud_model->get_blog_categories($blog['blog_category_id'])->row('title'); ?></td>
                                    <td>
                                        <span class="tqa-badge tqa-badge--danger"><?php echo get_phrase($blog['status']); ?></span>
                                    </td>
                                    <td>
                                        <div class="tqa-rowacts">
                                            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-toggle="dropdown">
                                                <i class="mdi mdi-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="confirm_modal('<?php echo site_url('admin/instructors_pending_blog/approval_request/' . $blog['blog_id']); ?>');">
                                                	<?php echo get_phrase('approved'); ?>
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" onclick="confirm_modal('<?php echo site_url('admin/instructors_pending_blog/delete/' . $blog['blog_id']); ?>');"><?php echo get_phrase('delete'); ?></a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div> <!-- end card body-->
        </div> <!-- end card -->
    </div><!-- end col-->
</div>