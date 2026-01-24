# Fooracles Management System - Theme Guide

## Overview
This theme system uses CSS custom properties (variables) to manage colors and styling across the entire application. All theme colors are centralized in `theme.css` for easy management and updates.

## File Structure
- `theme.css` - Primary theme file with all color variables and utilities
- `style.css` - Main stylesheet that uses theme variables
- `header.php` - Includes theme.css before style.css

## Brand Colors
The theme is based on these primary brand colors:

```css
--brand-primary: #2f3c7e;        /* Dark Blue */
--brand-secondary: #b85042;      /* Warm Red */
--brand-accent: #898d91;         /* Neutral Gray */
--brand-light: #fff2d7;          /* Cream White */
--brand-dark: #101820;           /* Dark Navy */
```

## How to Change Colors

### 1. Update Primary Brand Colors
To change the main brand colors, edit these variables in `theme.css`:

```css
:root {
    --brand-primary: #2f3c7e;        /* Change this */
    --brand-secondary: #b85042;      /* Change this */
    --brand-accent: #898d91;         /* Change this */
    --brand-light: #fff2d7;          /* Change this */
    --brand-dark: #101820;           /* Change this */
}
```

### 2. Color Variations
The theme automatically generates color variations (50, 100, 200, 300, 500, 600, 700, 800, 900) for each brand color. These are used throughout the application for:
- Hover states
- Background variations
- Border colors
- Shadow effects

### 3. Component-Specific Colors
Each component has its own color variables that reference the brand colors:

```css
/* Sidebar Colors */
--sidebar-bg: var(--gradient-sidebar);
--sidebar-text: var(--text-light);
--sidebar-active: var(--brand-secondary);

/* Header Colors */
--header-bg: var(--gradient-header);
--header-text: var(--text-light);

/* Button Colors */
--btn-primary-bg: var(--brand-primary);
--btn-secondary-bg: var(--brand-secondary);
```

## Available Color Variables

### Primary Colors
- `--brand-primary` - Main brand color
- `--brand-secondary` - Secondary brand color
- `--brand-accent` - Accent color
- `--brand-light` - Light background/text color
- `--brand-dark` - Dark text color

### Color Variations
Each color has 9 variations (50-900):
- `--primary-50` to `--primary-900`
- `--secondary-50` to `--secondary-900`
- `--accent-50` to `--accent-900`
- `--light-50` to `--light-900`

### Semantic Colors
- `--success` - Success states
- `--warning` - Warning states
- `--danger` - Error states
- `--info` - Information states

### Background Colors
- `--bg-primary` - Main background
- `--bg-secondary` - Secondary background
- `--bg-sidebar` - Sidebar background
- `--bg-header` - Header background
- `--bg-content` - Content area background

### Text Colors
- `--text-primary` - Primary text
- `--text-secondary` - Secondary text
- `--text-light` - Light text (on dark backgrounds)
- `--text-muted` - Muted text

## Utility Classes

The theme includes utility classes for quick styling:

```css
/* Background utilities */
.bg-primary { background-color: var(--brand-primary) !important; }
.bg-secondary { background-color: var(--brand-secondary) !important; }

/* Text utilities */
.text-primary { color: var(--text-primary) !important; }
.text-light { color: var(--text-light) !important; }

/* Border utilities */
.border-primary { border-color: var(--border-primary) !important; }
```

## Best Practices

1. **Always use theme variables** instead of hardcoded colors
2. **Use semantic color names** (e.g., `--text-primary` instead of `--brand-dark`)
3. **Test color changes** across all pages and components
4. **Maintain color contrast** for accessibility
5. **Use color variations** for hover states and interactions

## Example: Changing the Primary Color

To change the primary color from blue to green:

1. Open `assets/css/theme.css`
2. Find `--brand-primary: #2f3c7e;`
3. Change to `--brand-primary: #2d5a27;`
4. Save the file
5. Refresh the browser

The change will automatically apply to:
- Sidebar gradients
- Header gradients
- Active states
- Button colors
- Border colors
- All other components using the primary color

## Future Enhancements

The theme system is designed to support:
- Dark mode implementation
- Multiple theme variants
- User-customizable themes
- Accessibility improvements

## Troubleshooting

If colors don't update:
1. Check browser cache (Ctrl+F5)
2. Verify `theme.css` is included before `style.css`
3. Ensure CSS variables are properly defined
4. Check for CSS syntax errors

