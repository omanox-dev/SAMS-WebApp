# SAMS Theme Implementation Guide
## How to Add the Minimal Pastel Theme

### Implementation Steps

#### 1. Include the Theme CSS
Add this line to the `<head>` section of your HTML files:

```html
<!-- Add after Bootstrap CSS -->
<link rel="stylesheet" href="assets/css/pastel-theme.css">
```

#### 2. Update Your Main Layout Files
Add these classes to your main containers:

**For Login Page:**
```html
<div class="login-container">
  <div class="login-card">
    <div class="login-header">
      <h2 class="login-title">SAMS Login</h2>
      <p class="login-subtitle">Student Attendance Management System</p>
    </div>
    <!-- Login form content -->
  </div>
</div>
```

**For Dashboard Pages:**
```html
<div class="container-fluid bg-pastel-primary">
  <!-- Your dashboard content -->
</div>
```

#### 3. Update Navigation
Replace your navbar classes:
```html
<nav class="navbar navbar-expand-lg">
  <a class="navbar-brand" href="#">SAMS</a>
  <div class="navbar-nav">
    <a class="nav-link active" href="dashboard.php">Dashboard</a>
    <a class="nav-link" href="attendance.php">Attendance</a>
    <a class="nav-link" href="reports.php">Reports</a>
  </div>
</nav>
```

#### 4. Update Cards and Components
**For Statistics Cards:**
```html
<div class="col-md-3">
  <div class="stats-card">
    <div class="stats-number">150</div>
    <div class="stats-label">Total Students</div>
  </div>
</div>
```

**For Attendance Status:**
```html
<span class="attendance-present">Present</span>
<span class="attendance-absent">Absent</span>
<span class="attendance-late">Late</span>
```

#### 5. Form Styling
Your existing Bootstrap forms will automatically get the pastel styling. For enhanced styling:
```html
<div class="mb-3">
  <label class="form-label">Student Name</label>
  <input type="text" class="form-control" placeholder="Enter student name">
</div>
```

### 🎨 Color Reference

Use these CSS variables in your custom styles:

```css
/* Available Color Variables */
var(--bg-primary)          /* #FFF8F7 - Pale blush */
var(--bg-secondary)        /* #FFF5EB - Light peach */
var(--accent-primary)      /* #A7C7E7 - Powder blue */
var(--accent-secondary)    /* #E6B7C6 - Dusty rose */
var(--text-primary)        /* #2B2B2B - Dark grey */
var(--success-color)       /* #B8E6B8 - Soft mint */
var(--warning-color)       /* #FFE4B5 - Soft peach */
var(--danger-color)        /* #F5B7B1 - Soft coral */
```

### 🔧 Theme Toggle Implementation

Add this button to enable theme switching:

```html
<button class="theme-toggle" onclick="toggleTheme()" title="Switch Theme">
  🎨
</button>
```

JavaScript for theme switching:
```javascript
function toggleTheme() {
  const currentTheme = localStorage.getItem('theme') || 'default';
  const newTheme = currentTheme === 'default' ? 'pastel' : 'default';
  
  if (newTheme === 'pastel') {
    document.head.insertAdjacentHTML('beforeend', 
      '<link rel="stylesheet" href="assets/css/pastel-theme.css" id="pastel-theme">');
  } else {
    const pastelTheme = document.getElementById('pastel-theme');
    if (pastelTheme) pastelTheme.remove();
  }
  
  localStorage.setItem('theme', newTheme);
}

// Load saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'pastel') {
    document.head.insertAdjacentHTML('beforeend', 
      '<link rel="stylesheet" href="assets/css/pastel-theme.css" id="pastel-theme">');
  }
});
```

### 📱 Mobile Responsiveness

The theme includes responsive design that works perfectly on:
- ✅ Desktop (1920x1080+)
- ✅ Laptop (1366x768+)
- ✅ Tablet (768x1024)
- ✅ Mobile (375x667+)

### 🎯 Key Features

- **Gentle on Eyes**: Soft pastel colors reduce eye strain
- **Modern Aesthetic**: Rounded corners, subtle shadows, smooth transitions
- **Uplifting Feel**: Light, airy design promotes positive user experience
- **Fully Responsive**: Adapts beautifully to all screen sizes
- **Bootstrap Compatible**: Works seamlessly with your existing Bootstrap components
- **Smooth Animations**: Subtle hover effects and transitions
- **Accessibility**: Maintains good contrast ratios for readability

### 🚀 Quick Implementation

1. Copy `pastel-theme.css` to your `assets/css/` folder
2. Add the CSS link to your header
3. Your SAMS will instantly have the beautiful pastel theme!

No other changes needed - the theme automatically styles all your existing Bootstrap components with the minimal pastel aesthetic! ✨