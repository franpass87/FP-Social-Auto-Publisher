# Menu Structure Documentation

## New WordPress Admin Menu Organization

The Social Auto Publisher plugin now uses a consolidated menu structure to improve navigation and user experience.

### Main Menu: "Social Auto Publisher"

All plugin functionality is now organized under a single main menu item called "Social Auto Publisher" with the following submenus:

#### 1. Dashboard (Main Page)
- **Purpose**: Overview of plugin status and quick access to all features
- **Features**:
  - Statistics cards showing total posts, active clients, scheduled posts, and posts published today
  - Recent social posts table with status and details
  - Quick action buttons for easy navigation to other sections
  - Responsive design with modern styling

#### 2. Clienti (Clients)
- **Purpose**: Manage and view all configured clients
- **Features**:
  - List of all clients with direct links to their social posts
  - Client status overview

#### 3. Client Wizard
- **Purpose**: Step-by-step client configuration
- **Features**:
  - Multi-step wizard for setting up new clients
  - Trello integration configuration
  - Social media platform connections (Facebook, Instagram, YouTube, TikTok)
  - List mapping configuration

#### 4. Social Post
- **Purpose**: Manage all social media posts
- **Features**:
  - Comprehensive list of all social posts
  - Filtering by client and approval status
  - Post status tracking (scheduled, published, etc.)
  - Bulk actions for approval/revocation
  - Direct publishing capability

#### 5. Calendario (Calendar)
- **Purpose**: Visual calendar view of scheduled posts
- **Features**:
  - Monthly calendar view
  - Posts organized by date with time information
  - Channel information for each post
  - Navigation between months
  - Post count summary
  - Improved styling with better visual organization

#### 6. Analytics
- **Purpose**: Performance metrics and data visualization
- **Features**:
  - Summary statistics (total interactions, active channels, top channel, date range)
  - Interactive filtering by channel and date range
  - Chart visualization of engagement data
  - CSV export functionality
  - Responsive design

#### 7. Stato (Health Status)
- **Purpose**: System health monitoring
- **Features**:
  - Overall health percentage with visual indicator
  - Token validation for all clients
  - System checks (Trello webhooks, Action Scheduler)
  - WordPress requirements verification
  - Color-coded status indicators (green for OK, red for errors, orange for warnings)

#### 8. Log
- **Purpose**: System logs and debugging information
- **Features**:
  - Filterable log entries by channel and status
  - Detailed logging information
  - Log deletion capabilities
  - Pagination for large log sets

## Benefits of the New Structure

1. **Better Organization**: All functionality is grouped under one main menu item instead of scattered across multiple top-level menus
2. **Improved Navigation**: Logical hierarchy makes it easier to find specific features
3. **Enhanced User Experience**: Modern styling and responsive design
4. **Better Overview**: Dashboard provides quick access to key information and actions
5. **Consistent Design**: Unified styling across all pages

## CSS Styling

The plugin now includes dedicated CSS files for enhanced visual presentation:
- `tts-dashboard.css`: Dashboard page styling
- `tts-calendar.css`: Calendar page styling  
- `tts-health.css`: Health status page styling
- `tts-analytics.css`: Analytics page styling

All styles are responsive and follow WordPress admin design patterns for consistency.