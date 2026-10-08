<?php
// make sure session already started in parent page
$total_users = $total_users ?? 0;
?>

 <style>
        :root {
            --blue: #0066cc;
            --blue-dark: #0052a3;
            --blue-light: #eef6ff;
            --bg: #f4f8fc;
            --white: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #94a3b8;
            --sidebar-width: 260px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden; /* Prevent double scrollbar */
        }

        /* HEADER */
        .page-header {
            background: var(--blue);
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            z-index: 10;
            flex-shrink: 0;
        }

        .page-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-header h1 {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
        }

        .user-count-pill {
            background: rgba(255,255,255,0.2);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* MAIN LAYOUT (SIDEBAR + CONTENT) */
        .main-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--white);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            flex-shrink: 0;
            overflow-y: auto;
        }

        .sidebar-section { margin-bottom: 25px; }

        .sidebar-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            padding: 0 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar ul { list-style: none; }

        .sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            gap: 12px;
        }

        .sidebar ul li a i { font-size: 18px; color: var(--muted); }

        .sidebar ul li:hover a {
            background: var(--blue-light);
            color: var(--blue);
        }

        .sidebar ul li.active a {
            background: var(--blue-light);
            color: var(--blue);
            border-right: 4px solid var(--blue);
        }

        .sidebar ul li.active a i { color: var(--blue); }

        .sidebar ul li a span {
            margin-left: auto;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            color: #64748b;
        }

        /* MAIN CONTENT AREA */
        .wrapper {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: var(--bg);
        }

        /* TOOLBAR */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }
	 
	 /* REMOVE BOOTSTRAP GAP ONLY IN SIDEBAR */
.sidebar ul {
    padding-left: 0 !important;
    margin: 0 !important;
}

.sidebar ul li {
    list-style: none;
    margin: 0;
    padding: 0;
}

/* ENSURE FULL WIDTH */
.sidebar ul li a {
    display: flex;
    width: 100%;
}

/* ACTIVE FULL BLOCK */
.sidebar ul li.active {
    background: #eef6ff;
    border-right: 4px solid #0066cc;
}

.sidebar ul li.active a {
    background: transparent;
    color: #0066cc;
}
       
    </style>

<aside class="sidebar">
    <div class="sidebar-section">
        <p class="sidebar-title">People Management</p>
        <ul>
          <li class="<?= ($current_page == 'userlist.php') ? 'active' : '' ?>">
    <a href="userlist.php">
        <i class="bi bi-people"></i> People 
        <span><?= $total_users ?></span>
    </a>
</li>

		<li class="<?= ($current_page == 'team.php') ? 'active' : '' ?>">
    <a href="team.php">
        <i class="bi bi-diagram-3"></i> Teams
    </a>
</li>
			
		<li class="<?= ($current_page == 'createacc.php') ? 'active' : '' ?>">
    <a href="createacc.php">
      <i class="bi bi-person-plus"></i> Create Account
    </a>
</li>
			
           
        </ul>
    </div>


</aside>