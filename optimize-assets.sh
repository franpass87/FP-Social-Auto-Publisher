#!/bin/bash

# FP Publisher Asset Optimization Script
# This script optimizes CSS and JavaScript files for production

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Plugin directory
PLUGIN_DIR="wp-content/plugins/trello-social-auto-publisher"

echo -e "${GREEN}FP Publisher Asset Optimization${NC}"
echo "========================================"

# Check if we're in the right directory
if [ ! -d "$PLUGIN_DIR" ]; then
    echo -e "${RED}Error: Plugin directory not found. Please run this script from the repository root.${NC}"
    exit 1
fi

# Create optimized directory if it doesn't exist
mkdir -p "$PLUGIN_DIR/admin/css/optimized"
mkdir -p "$PLUGIN_DIR/admin/js/optimized"

echo -e "${YELLOW}Optimizing CSS files...${NC}"

# Combine and minify CSS files (basic version)
combine_css() {
    local output_file="$PLUGIN_DIR/admin/css/optimized/combined.min.css"
    local temp_file="/tmp/combined.css"
    
    # Combine CSS files in order of dependency
    cat > "$temp_file" << 'EOF'
/* Combined and optimized CSS for FP Publisher */
EOF
    
    cat "$PLUGIN_DIR/admin/css/tts-optimized.css" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/css/tts-dashboard.css" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/css/tts-analytics.css" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/css/tts-calendar.css" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/css/tts-health.css" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/css/tts-social-connections.css" >> "$temp_file"
    
    # Basic minification (remove comments and extra whitespace)
    sed 's|/\*.*\*/||g; s/  */ /g; s/; /;/g; s/ {/{/g; s/{ /{/g; s/ }/}/g' "$temp_file" > "$output_file"
    
    # Remove empty lines
    sed -i '/^$/d' "$output_file"
    
    echo "  ✓ CSS files combined and minified: $(basename "$output_file")"
    
    # Clean up
    rm "$temp_file"
}

# Combine and minify JavaScript files
combine_js() {
    local output_file="$PLUGIN_DIR/admin/js/optimized/combined.min.js"
    local temp_file="/tmp/combined.js"
    
    # Combine JS files in order of dependency
    echo "/* Combined and optimized JavaScript for FP Publisher */" > "$temp_file"
    
    cat "$PLUGIN_DIR/admin/js/tts-optimized-core.js" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/js/tts-dashboard.js" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/js/tts-analytics.js" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/js/tts-calendar.js" >> "$temp_file"
    cat "$PLUGIN_DIR/admin/js/tts-social-connections.js" >> "$temp_file"
    
    # Basic minification (remove comments and extra whitespace)
    sed 's|//.*||g; s|/\*.*\*/||g; s/  */ /g' "$temp_file" > "$output_file"
    
    # Remove empty lines
    sed -i '/^$/d' "$output_file"
    
    echo "  ✓ JavaScript files combined and minified: $(basename "$output_file")"
    
    # Clean up
    rm "$temp_file"
}

# Optimize images (if any)
optimize_images() {
    local image_dir="$PLUGIN_DIR/admin/images"
    
    if [ -d "$image_dir" ]; then
        echo -e "${YELLOW}Optimizing images...${NC}"
        
        # Find and list images that could be optimized
        find "$image_dir" -type f \( -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" \) -exec ls -lh {} \; | \
        while read -r line; do
            echo "  Found: $(echo "$line" | awk '{print $9}') - $(echo "$line" | awk '{print $5}')"
        done
        
        echo "  Note: For production, consider using tools like imagemin or tinypng for better compression"
    fi
}

# Generate performance report
generate_report() {
    echo -e "${YELLOW}Generating optimization report...${NC}"
    
    local report_file="$PLUGIN_DIR/optimization-report.txt"
    
    cat > "$report_file" << EOF
FP Publisher Optimization Report
Generated: $(date)
===============================

File Sizes Before/After Optimization:
EOF
    
    # CSS files
    echo "" >> "$report_file"
    echo "CSS Files:" >> "$report_file"
    for css_file in "$PLUGIN_DIR/admin/css"/*.css; do
        if [ -f "$css_file" ]; then
            local size=$(stat -f%z "$css_file" 2>/dev/null || stat -c%s "$css_file" 2>/dev/null || echo "0")
            echo "  $(basename "$css_file"): ${size} bytes" >> "$report_file"
        fi
    done
    
    if [ -f "$PLUGIN_DIR/admin/css/optimized/combined.min.css" ]; then
        local opt_size=$(stat -f%z "$PLUGIN_DIR/admin/css/optimized/combined.min.css" 2>/dev/null || stat -c%s "$PLUGIN_DIR/admin/css/optimized/combined.min.css" 2>/dev/null || echo "0")
        echo "  combined.min.css: ${opt_size} bytes" >> "$report_file"
    fi
    
    # JavaScript files
    echo "" >> "$report_file"
    echo "JavaScript Files:" >> "$report_file"
    for js_file in "$PLUGIN_DIR/admin/js"/*.js; do
        if [ -f "$js_file" ]; then
            local size=$(stat -f%z "$js_file" 2>/dev/null || stat -c%s "$js_file" 2>/dev/null || echo "0")
            echo "  $(basename "$js_file"): ${size} bytes" >> "$report_file"
        fi
    done
    
    if [ -f "$PLUGIN_DIR/admin/js/optimized/combined.min.js" ]; then
        local opt_size=$(stat -f%z "$PLUGIN_DIR/admin/js/optimized/combined.min.js" 2>/dev/null || stat -c%s "$PLUGIN_DIR/admin/js/optimized/combined.min.js" 2>/dev/null || echo "0")
        echo "  combined.min.js: ${opt_size} bytes" >> "$report_file"
    fi
    
    echo "" >> "$report_file"
    echo "Optimization completed successfully!" >> "$report_file"
    
    echo "  ✓ Report generated: optimization-report.txt"
}

# Run optimizations
combine_css
echo ""

echo -e "${YELLOW}Optimizing JavaScript files...${NC}"
combine_js
echo ""

optimize_images
echo ""

generate_report
echo ""

echo -e "${GREEN}Optimization completed!${NC}"
echo ""
echo "Next steps:"
echo "1. Test the optimized files in a staging environment"
echo "2. Update the plugin to use optimized files in production"
echo "3. Consider setting up automated optimization in your build process"
echo ""
echo "For better compression, consider using:"
echo "- UglifyJS or Terser for JavaScript minification"
echo "- CSSnano or clean-css for CSS optimization"
echo "- ImageOptim or similar tools for image compression"