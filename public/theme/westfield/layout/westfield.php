<?php

defined('MOODLE_INTERNAL') || die();

global $PAGE, $OUTPUT, $SITE, $USER, $CFG;

$isadmin = is_siteadmin($USER);

$bodyclass = $isadmin
    ? 'westfield-admin-layout'
    : 'westfield-has-sidebar';

$bodyattributes = $OUTPUT->body_attributes([
    'class' => $bodyclass
]);

$fullname = fullname($USER);

$userrole = $isadmin ? 'Administrator' : 'Student';

$initials = '';

if (!empty($USER->firstname)) {
    $initials .= strtoupper(
        substr($USER->firstname, 0, 1)
    );
}

if (!empty($USER->lastname)) {
    $initials .= strtoupper(
        substr($USER->lastname, 0, 1)
    );
}

if (empty($initials)) {
    $initials = 'U';
}


/* =========================================================
   URLS
   ========================================================= */

$dashboardurl = new moodle_url('/my/');

$homeurl = new moodle_url('/');

$calendarurl = new moodle_url(
    '/calendar/view.php',
    ['view' => 'month']
);

$filesurl = new moodle_url('/user/files.php');

$coursesurl = new moodle_url('/my/courses.php');

$profileurl = new moodle_url(
    '/user/profile.php',
    ['id' => $USER->id]
);

$gradesurl = new moodle_url('/grade/report/overview/index.php');

$messagesurl = new moodle_url('/message/index.php');

$preferencesurl = new moodle_url('/user/preferences.php');

$logouturl = new moodle_url(
    '/login/logout.php',
    ['sesskey' => sesskey()]
);

$secondarynavigation = false;

if ($isadmin && $PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu(
        $PAGE->secondarynav,
        'nav-tabs',
        true,
        $tablistnav
    );
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
}


echo $OUTPUT->doctype();
?>

<html <?php echo $OUTPUT->htmlattributes(); ?>>

<head>

    <title>
        <?php echo $OUTPUT->page_title(); ?>
    </title>

    <?php echo $OUTPUT->standard_head_html(); ?>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link
        rel="icon"
        type="image/x-icon"
        href="<?php echo $CFG->wwwroot; ?>/theme/westfield/pix/fav.png"
    >

</head>


<body <?php echo $bodyattributes; ?>>

<?php echo $OUTPUT->standard_top_of_body_html(); ?>


<!-- =====================================================
     WESTFIELD NAVBAR
     ===================================================== -->

<header class="westfield-navbar">

    <!-- LEFT -->

    <div class="westfield-navbar-left">

        <!-- Sidebar Toggle - Students only -->

        <?php if (!$isadmin): ?>

            <button
                type="button"
                class="westfield-menu-button"
                id="westfield-sidebar-toggle"
                aria-label="Toggle sidebar"
                aria-expanded="true"
            >
                <i class="fa fa-bars"></i>
            </button>

        <?php endif; ?>


        <!-- Brand -->

        <a
            href="<?php echo $dashboardurl; ?>"
            class="westfield-brand"
        >

            <img
                src="<?php echo $CFG->wwwroot; ?>/theme/westfield/pix/logo.png"
                alt="WestField Campus"
                class="westfield-navbar-logo"
            >

            <span class="westfield-brand-text">
                WestField LMS
            </span>

        </a>

        <?php if ($isadmin): ?>

            <nav class="westfield-admin-navbar-links">

                <a
                    href="<?php echo $dashboardurl; ?>"
                    class="westfield-admin-navbar-link"
                >
                    <i class="fa fa-dashboard"></i>
                    <span>Dashboard</span>
                </a>

                <a
                    href="<?php echo $CFG->wwwroot; ?>/admin/search.php"
                    class="westfield-admin-navbar-link"
                >
                    <i class="fa fa-cogs"></i>
                    <span>Site administration</span>
                </a>

            </nav>

        <?php endif; ?>

    </div>


    <!-- RIGHT -->

    <div class="westfield-navbar-right">


        <!-- Moodle notification / message output -->

        <div class="westfield-navbar-plugins">

            <?php echo $OUTPUT->navbar_plugin_output(); ?>

        </div>


        <!-- =================================================
             USER DROPDOWN
             ================================================= -->

        <div
            class="westfield-user-menu"
            id="westfield-user-menu"
        >

            <button
                type="button"
                class="westfield-user-trigger"
                id="westfield-user-trigger"
                aria-expanded="false"
                aria-haspopup="true"
            >

                <!-- Avatar -->

                <div class="westfield-user-avatar">

                    <?php echo s($initials); ?>

                </div>


                <!-- Name -->

                <div class="westfield-user-info">

                    <span class="westfield-user-name">

                        <?php echo s($fullname); ?>

                    </span>

                    <span class="westfield-user-role">
                        <?php echo s($userrole); ?>
                    </span>

                </div>


                <!-- Arrow -->

                <i
                    class="fa fa-chevron-down westfield-user-chevron"
                ></i>

            </button>


            <!-- =============================================
                 DROPDOWN
                 ============================================= -->

            <div
                class="westfield-user-dropdown"
                id="westfield-user-dropdown"
                role="menu"
            >


                <!-- User header -->

                <div class="westfield-dropdown-user">

                    <div class="westfield-dropdown-avatar">

                        <?php echo s($initials); ?>

                    </div>

                    <div class="westfield-dropdown-user-details">

                        <div class="westfield-dropdown-name">

                            <?php echo s($fullname); ?>

                        </div>

                        <div class="westfield-dropdown-role">
                            <?php echo s($userrole); ?>
                        </div>

                    </div>

                </div>


                <div class="westfield-dropdown-divider"></div>


                <!-- Dashboard -->

                <a
                    href="<?php echo $dashboardurl; ?>"
                    class="westfield-dropdown-item"
                >

                    <span class="westfield-dropdown-icon">
                        <i class="fa fa-gauge-high fa-tachometer-alt"></i>
                    </span>

                    <span>
                        Dashboard
                    </span>

                </a>


                <!-- Profile -->

                <a
                    href="<?php echo $profileurl; ?>"
                    class="westfield-dropdown-item"
                >

                    <span class="westfield-dropdown-icon">
                        <i class="fa fa-user"></i>
                    </span>

                    <span>
                        Profile
                    </span>

                </a>


                <!-- Grades -->

                <a
                    href="<?php echo $gradesurl; ?>"
                    class="westfield-dropdown-item"
                >

                    <span class="westfield-dropdown-icon">
                        <i class="fa fa-book-open fa-book"></i>
                    </span>

                    <span>
                        Grades
                    </span>

                </a>


                <!-- Messages -->

                <a
                    href="<?php echo $messagesurl; ?>"
                    class="westfield-dropdown-item"
                >

                    <span class="westfield-dropdown-icon">
                        <i class="fa fa-comment"></i>
                    </span>

                    <span>
                        Messages
                    </span>

                </a>


                <!-- Preferences -->

                <a
                    href="<?php echo $preferencesurl; ?>"
                    class="westfield-dropdown-item"
                >

                    <span class="westfield-dropdown-icon">
                        <i class="fa fa-wrench"></i>
                    </span>

                    <span>
                        Preferences
                    </span>

                </a>


                <div class="westfield-dropdown-divider"></div>


                <!-- Logout -->

                <a
                    href="<?php echo $logouturl; ?>"
                    class="westfield-dropdown-item westfield-dropdown-logout"
                >

                    <span class="westfield-dropdown-icon">
                        <i class="fa fa-sign-out"></i>
                    </span>

                    <span>
                        Log out
                    </span>

                </a>

            </div>

        </div>

    </div>

</header>



<!-- =====================================================
     WESTFIELD SIDEBAR
     STUDENT / NON-ADMIN USERS ONLY
     ===================================================== -->

<?php if (!$isadmin): ?>

<aside
    class="westfield-sidebar"
    id="westfield-sidebar"
>

    <div class="westfield-sidebar-content">


        <!-- Dashboard -->

        <a
            href="<?php echo $dashboardurl; ?>"
            class="westfield-sidebar-link"
        >

            <span class="westfield-sidebar-icon">
                <i class="fa fa-gauge-high fa-tachometer-alt"></i>
            </span>

            <span class="westfield-sidebar-label">
                Dashboard
            </span>

        </a>


        <!-- Calendar -->

        <a
            href="<?php echo $calendarurl; ?>"
            class="westfield-sidebar-link"
        >

            <span class="westfield-sidebar-icon">
                <i class="fa fa-calendar"></i>
            </span>

            <span class="westfield-sidebar-label">
                Calendar
            </span>

        </a>


        <!-- Private Files -->

        <a
            href="<?php echo $filesurl; ?>"
            class="westfield-sidebar-link"
        >

            <span class="westfield-sidebar-icon">
                <i class="fa fa-file"></i>
            </span>

            <span class="westfield-sidebar-label">
                Private files
            </span>

        </a>


        <div class="westfield-sidebar-divider"></div>


        <!-- My Courses -->

        <a
            href="<?php echo $coursesurl; ?>"
            class="westfield-sidebar-link"
        >

            <span class="westfield-sidebar-icon">
                <i class="fa fa-graduation-cap"></i>
            </span>

            <span class="westfield-sidebar-label">
                My courses
            </span>

            <span class="westfield-sidebar-arrow">
                <i class="fa fa-chevron-right"></i>
            </span>

        </a>


        <!-- Profile -->

        <a
            href="<?php echo $profileurl; ?>"
            class="westfield-sidebar-link"
        >

            <span class="westfield-sidebar-icon">
                <i class="fa fa-user"></i>
            </span>

            <span class="westfield-sidebar-label">
                Profile
            </span>

        </a>


        <!-- Grades -->

        <a
            href="<?php echo $gradesurl; ?>"
            class="westfield-sidebar-link"
        >

            <span class="westfield-sidebar-icon">
                <i class="fa fa-book"></i>
            </span>

            <span class="westfield-sidebar-label">
                Grades
            </span>

        </a>


        <!-- Messages -->

        <a
            href="<?php echo $messagesurl; ?>"
            class="westfield-sidebar-link"
        >

            <span class="westfield-sidebar-icon">
                <i class="fa fa-comment"></i>
            </span>

            <span class="westfield-sidebar-label">
                Messages
            </span>

        </a>

    </div>


    <!-- =================================================
         SIDEBAR BOTTOM
         ================================================= -->

    <div class="westfield-sidebar-bottom">

        <a
            href="<?php echo $preferencesurl; ?>"
            class="westfield-sidebar-help"
        >

            <span class="westfield-sidebar-icon">
                <i class="fa fa-cog"></i>
            </span>

            <span>
                Preferences
            </span>

        </a>

    </div>

</aside>

<?php endif; ?>



<!-- MOBILE OVERLAY -->

<div
    class="westfield-sidebar-overlay"
    id="westfield-sidebar-overlay"
></div>



<!-- =====================================================
     MAIN APP
     ===================================================== -->

<div class="westfield-app">

    <div class="westfield-page-area">


        <!-- Page header -->

        <?php if ($isadmin): ?>

            <?php echo $OUTPUT->full_header(); ?>

        <?php else: ?>

            <div class="westfield-page-header">

                <?php if ($PAGE->has_set_url()): ?>

                    <div class="westfield-breadcrumb">

                        <?php echo $OUTPUT->navbar(); ?>

                    </div>

                <?php endif; ?>


                <?php if (!empty($PAGE->heading)): ?>

                    <h1 class="westfield-page-title">

                        <?php
                        echo format_string(
                            $PAGE->heading
                        );
                        ?>

                    </h1>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <?php if ($secondarynavigation): ?>

            <div class="westfield-admin-secondary-navigation d-print-none">
                <?php echo $OUTPUT->render_from_template('core/moremenu', $secondarynavigation); ?>
            </div>

        <?php endif; ?>


        <!-- Main content -->

        <main
            id="region-main"
            class="westfield-main-content"
            aria-label="Content"
        >

            <?php echo $OUTPUT->main_content(); ?>

        </main>


        <?php echo $OUTPUT->standard_after_main_region_html(); ?>


       <!-- =====================================================
             WESTFIELD CAMPUS LMS FOOTER
        ===================================================== -->

        <footer class="westfield-footer">

            <!-- =================================================
                MAIN FOOTER
                ================================================= -->

            <div class="westfield-footer-container">

                <!-- =============================================
                    BRAND
                    ============================================= -->

                <div class="westfield-footer-brand">

                    <div class="westfield-footer-brand-row">

                        <img
                            src="<?php echo $CFG->wwwroot; ?>/theme/westfield/pix/logo.png"
                            alt="WestField Campus"
                            class="westfield-footer-logo"
                        >

                        <div class="westfield-footer-brand-content">

                            <div class="westfield-footer-title">
                                WestField Campus
                            </div>

                            <div class="westfield-footer-subtitle">
                                Learning Management System
                            </div>

                        </div>

                    </div>


                    <p class="westfield-footer-description">
                        Shaping Careers Through Practical Education
                    </p>

                </div>


                <!-- =============================================
                    QUICK LINKS
                    ============================================= -->

                <div class="westfield-footer-section">

                    <h6>
                        Quick Links
                    </h6>


                    <a href="<?php echo $dashboardurl; ?>">

                        <span class="westfield-footer-link-icon">
                            <i class="fa fa-dashboard"></i>
                        </span>

                        <span>
                            Dashboard
                        </span>

                    </a>


                    <a href="<?php echo $coursesurl; ?>">

                        <span class="westfield-footer-link-icon">
                            <i class="fa fa-graduation-cap"></i>
                        </span>

                        <span>
                            My Courses
                        </span>

                    </a>


                    <a href="<?php echo $profileurl; ?>">

                        <span class="westfield-footer-link-icon">
                            <i class="fa fa-user"></i>
                        </span>

                        <span>
                            My Profile
                        </span>

                    </a>

                </div>


                <!-- =============================================
                    WESTFIELD CAMPUS
                    ============================================= -->

                <div class="westfield-footer-section">

                    <h6>
                        WestField Campus
                    </h6>


                    <a
                        href="https://westfieldcampus.lk/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >

                        <span class="westfield-footer-link-icon">
                            <i class="fa fa-globe"></i>
                        </span>

                        <span>
                            Official Website
                        </span>

                    </a>


                    <a href="mailto:info@westfieldcampus.lk">

                        <span class="westfield-footer-link-icon">
                            <i class="fa fa-envelope"></i>
                        </span>

                        <span>
                            info@westfieldcampus.lk
                        </span>

                    </a>


                    <a href="tel:+94112607323">

                        <span class="westfield-footer-link-icon">
                            <i class="fa fa-phone"></i>
                        </span>

                        <span>
                            +94 11 260 7323
                        </span>

                    </a>

                </div>

            </div>


            <!-- =================================================
                BOTTOM BAR
                ================================================= -->

            <div class="westfield-footer-bottom">

                <!-- Copyright -->

                <div class="westfield-footer-copyright">

                    <span>
                        &copy; <?php echo date('Y'); ?>
                    </span>

                    <strong>
                        WestField Campus.
                    </strong>

                    <span>
                        All rights reserved.
                    </span>

                </div>


                <!-- Legal / Website -->

                <div class="westfield-footer-bottom-links">

                    <a
                        href="https://westfieldcampus.lk/privacy"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Privacy Policy
                    </a>

                    <span class="westfield-footer-dot"></span>

                    <a
                        href="https://westfieldcampus.lk/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        westfieldcampus.lk
                    </a>

                </div>

            </div>

        </footer>

    </div>

</div>



<!-- =====================================================
     WESTFIELD JS
     ===================================================== -->

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {

        /* ================================================
           ELEMENTS
           ================================================ */

        const body =
            document.body;

        const sidebar =
            document.getElementById(
                'westfield-sidebar'
            );

        const sidebarToggle =
            document.getElementById(
                'westfield-sidebar-toggle'
            );

        const overlay =
            document.getElementById(
                'westfield-sidebar-overlay'
            );

        const userMenu =
            document.getElementById(
                'westfield-user-menu'
            );

        const userTrigger =
            document.getElementById(
                'westfield-user-trigger'
            );

        const userDropdown =
            document.getElementById(
                'westfield-user-dropdown'
            );


        /* ================================================
           SIDEBAR
           ================================================ */

        function isMobile() {

            return window.innerWidth <= 991.98;

        }


        function openMobileSidebar() {

            if (!sidebar) {
                return;
            }

            sidebar.classList.add(
                'westfield-sidebar-open'
            );

            if (overlay) {

                overlay.classList.add(
                    'westfield-overlay-open'
                );

            }

            body.classList.add(
                'westfield-mobile-sidebar-open'
            );

            if (sidebarToggle) {

                sidebarToggle.setAttribute(
                    'aria-expanded',
                    'true'
                );

            }

        }


        function closeMobileSidebar() {

            if (!sidebar) {
                return;
            }

            sidebar.classList.remove(
                'westfield-sidebar-open'
            );

            if (overlay) {

                overlay.classList.remove(
                    'westfield-overlay-open'
                );

            }

            body.classList.remove(
                'westfield-mobile-sidebar-open'
            );

            if (sidebarToggle) {

                sidebarToggle.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }


        function toggleDesktopSidebar() {

            body.classList.toggle(
                'westfield-sidebar-collapsed'
            );

            const collapsed =
                body.classList.contains(
                    'westfield-sidebar-collapsed'
                );


            if (sidebarToggle) {

                sidebarToggle.setAttribute(
                    'aria-expanded',
                    collapsed ? 'false' : 'true'
                );

            }


            /* Save preference */

            try {

                localStorage.setItem(
                    'westfieldSidebarCollapsed',
                    collapsed ? '1' : '0'
                );

            } catch (error) {
                // Ignore storage errors.
            }

        }


        /* Restore desktop preference */

        if (!isMobile()) {

            try {

                const savedState =
                    localStorage.getItem(
                        'westfieldSidebarCollapsed'
                    );

                if (savedState === '1') {

                    body.classList.add(
                        'westfield-sidebar-collapsed'
                    );

                    if (sidebarToggle) {

                        sidebarToggle.setAttribute(
                            'aria-expanded',
                            'false'
                        );

                    }

                }

            } catch (error) {
                // Ignore storage errors.
            }

        }


        if (sidebarToggle) {

            sidebarToggle.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    if (isMobile()) {

                        if (
                            sidebar &&
                            sidebar.classList.contains(
                                'westfield-sidebar-open'
                            )
                        ) {

                            closeMobileSidebar();

                        } else {

                            openMobileSidebar();

                        }

                    } else {

                        toggleDesktopSidebar();

                    }

                }
            );

        }


        if (overlay) {

            overlay.addEventListener(
                'click',
                closeMobileSidebar
            );

        }

        /* ================================================
        ACTIVE SIDEBAR ITEM
        ================================================ */

        const currentPath =
            window.location.pathname;

        const sidebarLinks =
            document.querySelectorAll(
                '.westfield-sidebar-link[href]'
            );

        sidebarLinks.forEach(
            function (link) {

                try {

                    const linkURL =
                        new URL(
                            link.href,
                            window.location.origin
                        );

                    if (
                        linkURL.pathname !== '/' &&
                        currentPath.startsWith(
                            linkURL.pathname
                        )
                    ) {

                        link.classList.add(
                            'active'
                        );

                    }

                } catch (error) {
                    // Ignore invalid URLs.
                }

            }
        );

        /* ================================================
           USER DROPDOWN
           ================================================ */

        function closeUserDropdown() {

            if (!userMenu) {
                return;
            }

            userMenu.classList.remove(
                'westfield-user-menu-open'
            );

            if (userTrigger) {

                userTrigger.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }


        function toggleUserDropdown() {

            if (!userMenu) {
                return;
            }

            const opened =
                userMenu.classList.toggle(
                    'westfield-user-menu-open'
                );

            if (userTrigger) {

                userTrigger.setAttribute(
                    'aria-expanded',
                    opened ? 'true' : 'false'
                );

            }

        }


        if (userTrigger) {

            userTrigger.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    toggleUserDropdown();

                }
            );

        }


        if (userDropdown) {

            userDropdown.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                }
            );

        }


        /* Click outside */

        document.addEventListener(
            'click',
            function () {

                closeUserDropdown();

            }
        );


        /* Escape */

        document.addEventListener(
            'keydown',
            function (event) {

                if (event.key === 'Escape') {

                    closeUserDropdown();

                    closeMobileSidebar();

                }

            }
        );


        /* Window resize */

        window.addEventListener(
            'resize',
            function () {

                if (!isMobile()) {

                    closeMobileSidebar();

                }

            }
        );

    }
);
</script>


<?php echo $OUTPUT->standard_end_of_body_html(); ?>

</body>
</html>