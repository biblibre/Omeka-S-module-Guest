<?php
$pageTitle = $this->translate('Browse Users') . ' ' . sprintf($this->translate('(%s total)', $total_results));
echo head(['title' => $pageTitle, 'bodyclass' => 'users']);
echo flash();
?>

<?php if (isset($_GET['search'])):?>
<div id='search-filters'>
    <ul>
        <li>
        <?php switch ($_GET['search-type']) {
                        case "name":
                            echo $this->translate("Name") . ': ';
                        break;
                        case "username":
                            echo $this->translate("Username") . ': ';
                        break;
                        case "email":
                            echo $this->translate("Email") . ': ';
                        break;
                    }
        ?>
        <?php echo html_escape($_GET['search']); ?>
        </li>
    </ul>
</div>
<?php endif; ?>


<?php if (isset($_GET['role'])):?>
<div id='search-filters'>
    <ul>
        <li>
        <?php echo html_escape($this->translate($_GET['role'])); ?>
        </li>
    </ul>

</div>
<?php endif; ?>



<form id='search-users' method='GET'>
<button><?php echo $this->translate('Search users'); ?></button><input type='text' name='search'/>
<input type='radio' name='search-type' value='username' checked='checked' /><span><?php echo $this->translate('Usernames'); ?></span>
<input type='radio' name='search-type' value='name' /><span><?php echo $this->translate('Real names'); ?></span>
<input type='radio' name='search-type' value='email' /><span><?php echo $this->translate('Email addresses'); ?></span>
</form>

<ul class='quick-filter-wrapper'>
    <li>
        <a tabindex="0" href="#"><?php echo $this->translate("Quick Filter"); ?></a>
        <ul class="dropdown">
            <li>
                <span class="quick-filter-heading"><?php echo $this->translate("Quick Filter"); ?></span>
            </li>
            <li>
                <a href="<?php echo url('guest-user/user/browse'); ?>"><?php echo $this->translate("View All"); ?></a>
            </li>
            <?php foreach (get_user_roles() as $value => $name): ?>
            <li>
                <a href="<?php echo url('guest-user/user/browse', ['role' => $value]); ?>"><?php echo $this->translate($name); ?></a>
            </li>
            <?php endforeach; ?>
            <li>
                <a href="<?php echo url('guest-user/user/browse', ['active' => 'true']); ?>"><?php echo $this->translate("Active"); ?></a>
            </li>
            <li>
                <a href="<?php echo url('guest-user/user/browse', ['active' => 'false']); ?>"><?php echo $this->translate("Not Active"); ?></a>
            </li>
        </ul>
    </li>
</ul>

<?php echo pagination_links(); ?>
<table id="users">
    <thead>
        <tr>
        <?php $sortLinks = [
                $this->translate('ID') => 'id',
                $this->translate('Username') => 'username',
                $this->translate('Real Name') => 'name',
                $this->translate('Email') => 'email',
                $this->translate('Role') => 'role',
                ];
        ?>
        <?php echo browse_sort_links($sortLinks, ['link_tag' => 'th scope="col"', 'list_tag' => '']); ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $key => $user): ?>
        <tr class="<?php if (current_user()->id == $user->id) {
            echo 'current-user ';
        } ?><?php if ($key % 2 == 1) {
            echo 'even';
        } else {
            echo 'odd';
        } ?><?php if (!$user->active): ?> inactive<?php endif; ?>">
            <td>
            <?php echo metadata($user, 'id'); ?>
            </td>
            <td>
            <?php echo html_escape($user->username); ?> <?php if (!$user->active): ?>(<?php echo $this->translate('inactive'); ?>)<?php endif; ?>
            <ul class="action-links group">
                <?php if (is_allowed($user, 'edit')): ?>
                <li><?php echo link_to($user, 'edit', $this->translate('Edit'), ['class' => 'edit']); ?></li>
                <?php endif; ?>
                <?php if (is_allowed($user, 'delete')): ?>
                <li><?php echo link_to($user, 'delete-confirm', $this->translate('Delete'), ['class' => 'delete']); ?></li>
                <?php endif; ?>
            </ul>
            <?php fire_plugin_hook('admin_users_browse_each', ['user' => $user, 'view' => $this]); ?>
           </td>
            <td><?php echo html_escape($user->name); ?></td>
            <td><?php echo html_escape($user->email); ?></td>
            <td><span class="<?php echo html_escape($user->role); ?>"><?php echo html_escape($this->translate(Inflector::humanize($user->role))); ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php echo pagination_links(); ?>
<?php fire_plugin_hook('admin_users_browse', ['users' => $users, 'view' => $this]); ?>
<?php echo foot();?>
