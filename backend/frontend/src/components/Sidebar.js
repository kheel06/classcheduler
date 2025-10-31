import { useLocation, useNavigate } from "react-router-dom";
import { useEffect } from "react";
import "./Sidebar.css";

const Sidebar = ({ collapsed }) => {
  // Keep sidebar width consistent with page layout defaults (250px when expanded)
  const sidebarWidth = collapsed ? 80 : 250;
  const location = useLocation();
  const { pathname } = location;
  const navigate = useNavigate();

  useEffect(() => {
    let timer;
    const resetTimer = () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        navigate("/logout");
      }, 20 * 60 * 1000); // 20 minutes
    };
    const events = ["mousemove", "keydown", "scroll", "click"];
    events.forEach((event) => window.addEventListener(event, resetTimer));
    resetTimer();
    return () => {
      clearTimeout(timer);
      events.forEach((event) => window.removeEventListener(event, resetTimer));
    };
  }, [navigate]);

  // Get user data from sessionStorage (fall back to placeholders)
  const firstName = sessionStorage.getItem("first_name") || "";
  const lastName = sessionStorage.getItem("last_name") || "";
  const rawUser = sessionStorage.getItem("user") || sessionStorage.getItem("username") || "";
  const displayName = (firstName || lastName)
    ? `${firstName} ${lastName}`.trim()
    : rawUser || "admin 4107";
  const email = sessionStorage.getItem("email") || "admin@example.com";
  const role = (sessionStorage.getItem("role") || sessionStorage.getItem("user_type") || "ADMIN").toString().toUpperCase();

  // Minimal nav items — layout/UI only. Keep links that belong to this app.
  const navGroups = [
    {
      category: "MAIN MENU",
      items: [
        { label: "Dashboard", icon: "bi-house-door", path: "/dashboard" },
        { label: "Calendar", icon: "bi-calendar2-week", path: "/calendar" },
      ],
    },
    {
      category: "ACADEMIC MANAGEMENT",
      items: [
        { label: "Classes", icon: "bi-journal", path: "/classes" },
        { label: "Subjects", icon: "bi-book", path: "/subjects" },
      ],
    },
    {
      category: "FACILITY MANAGEMENT",
      items: [
        { label: "Rooms", icon: "bi-door-open", path: "/rooms" },
      ],
    },
    {
      category: "ADMINISTRATION",
      items: [
        { label: "Activity", icon: "bi-clipboard-data", path: "/activity" },
        { label: "Users", icon: "bi-people", path: "/users" },
      ],
    },
    {
      category: "ACCOUNT",
      items: [
        { label: "Profile", icon: "bi-person-circle", path: "/profile" },
      ],
    },
  ];

  // Helper to compute initials
  const getInitials = (name) => {
    if (!name) return "A";
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  };

  // Keep CSS variable in sync so other parts of the app (main content, navbar)
  // can rely on --sidebar-width. This avoids layout mismatches.
  useEffect(() => {
    // Keep the CSS variables strictly in sync with the component's
    // sidebarWidth so layout consumers read the correct value.
    try {
      const doc = document.documentElement;
      doc.style.setProperty("--sidebar-width", `${sidebarWidth}px`);
      doc.style.setProperty("--sidebar-width-collapsed", `80px`);
    } catch (e) {
      // ignore in non-browser environments
    }
  }, [collapsed, sidebarWidth]);

  return (
    <div className={`sidebar ${collapsed ? "collapsed" : ""}`}>
      <div className="sidebar-profile">
        <div className="sidebar-avatar">{getInitials(displayName)}</div>
        {!collapsed && (
          <>
            <div className="sidebar-name">{displayName}</div>
            <div className="sidebar-email">{email}</div>
            <div className="sidebar-role">{role}</div>
          </>
        )}
      </div>

      <div className="sidebar-divider" />

      {navGroups.map((group) => (
        <div className="sidebar-section" key={group.category}>
          {!collapsed && <h6>{group.category}</h6>}
          <div className="sidebar-links">
            {group.items.map((item) => {
              const active = pathname === item.path || pathname.startsWith(item.path + "/");
              // When collapsed we center the icon and hide the label; when
              // expanded show the icon with some right margin.
              const linkClasses = `d-flex align-items-center sidebar-link ${active ? "active" : "text-white"} ${collapsed ? 'justify-content-center' : ''}`;
              const iconClasses = `bi ${item.icon} ${collapsed ? 'fs-5' : 'me-3 fs-6'}`.trim();
              return (
                <a
                  key={item.path}
                  href="#"
                  title={item.label}
                  onClick={(e) => {
                    e.preventDefault();
                    navigate(item.path);
                  }}
                  className={linkClasses}
                >
                  <i className={iconClasses} aria-hidden="true" />
                  {!collapsed && <span>{item.label}</span>}
                </a>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
};

export default Sidebar;
