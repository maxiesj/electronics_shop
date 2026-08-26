<!-- search_component.php -->
<style>
    /* ==========================================================================
       1. CORE SEARCH CONTAINER COMPONENTS (DEFAULT DESKTOP VIEW)
       ========================================================================== */
    .search-container {
        display: flex;
        align-items: center;
        max-width: 320px;
        width: 100%;
        position: relative;
        margin: 0 20px;
        box-sizing: border-box; /* Prevents container from breaking out of flex baselines */
    }
    
    /* Translucent Translucent Input Box Styling */
    .search-input {
        width: 100%;
        padding: 8px 40px 8px 14px;
        font-size: 13px;
        border: none;
        border-radius: 20px;
        background-color: rgba(255, 255, 255, 0.15); /* Clean white tint translucent style */
        color: #ffffff;
        outline: none;
        height: 36px; /* Explicit height baseline for cross-browser symmetry */
        box-sizing: border-box;
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }
    .search-input::placeholder {
        color: rgba(255, 255, 255, 0.65);
    }
    .search-input:focus {
        background-color: rgba(255, 255, 255, 0.25);
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
    }
    
    /* Absolute Position Trigger Button Icon */
    .search-btn {
        position: absolute;
        right: 4px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px;
        color: rgba(255, 255, 255, 0.75);
        display: flex;
        align-items: center;
        justify-content: center;
        height: 28px;
        width: 28px;
        border-radius: 50%;
        box-sizing: border-box;
        transition: color 0.2s ease, background-color 0.2s ease;
    }
    .search-btn:hover {
        color: #ffffff;
        background-color: rgba(255, 255, 255, 0.1);
    }

    /* Unmatched Matrix State Empty Feedback Block */
    .no-results-msg {
        text-align: center;
        color: #94a3b8;
        grid-column: 1 / -1; /* Forces full canvas expansion inside layout grids */
        padding: 40px 20px;
        font-size: 15px;
        font-weight: 500;
        box-sizing: border-box;
        width: 100%;
    }

    /* ==========================================================================
       2. RESPONSIVE MEDIA QUERIES (TABLETS & SMARTPHONES DISPLAY ADAPTATIONS)
       ========================================================================== */

    /* 📱 TRANSITIONAL PORTRAIT TABLETS BREAKPOINT (Max 768px Viewports) */
    @media screen and (max-width: 768px) {
        .search-container {
            max-width: 280px; /* Contracts widths slightly inside tablet toolbars */
            margin: 0 12px;
        }
    }

    /* 📱 MINI SMARTPHONE DISPLAY CONSTRAINTS (Max 640px Width Screens) */
    @media screen and (max-width: 640px) {
        /* Expand searching inputs to utilize 100% device width rules when inside navigation dropdown stacks */
        .search-container {
            max-width: 100%;
            margin: 8px 0; /* Creates distinct vertical separation blocks within mobile stacks */
        }
        
        /* Enlarge interactive input fields to avoid double-tap zoom overrides on touch panels */
        .search-input {
            height: 42px;
            font-size: 14px;
            padding: 10px 46px 10px 16px;
        }
        
        /* Reposition absolute icon click bounds to balance augmented input height profile */
        .search-btn {
            right: 6px;
            height: 32px;
            width: 32px;
        }
        
        .no-results-msg {
            font-size: 14px;
            padding: 32px 16px;
        }
    }
</style>


<div class="search-container">
    <input type="text" id="search-input" class="search-input" placeholder="Search brands or items...">
    <button type="button" class="search-btn">
        <svg xmlns="http://w3.org" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
        </svg>
    </button>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("search-input");
    const grid = document.getElementById("product-grid");
    const counter = document.getElementById("counter-wrap");

    if (!searchInput || !grid || !counter) return;

    // Create a dynamic 'No products found' container elements
    const fallbackNode = document.createElement("p");
    fallbackNode.className = "no-results-msg";
    fallbackNode.style.display = "none";
    fallbackNode.textContent = "No products match your search keyword.";
    grid.appendChild(fallbackNode);

    searchInput.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase().trim();
        const cards = grid.querySelectorAll(".prod-card");
        let visibleCount = 0;

        cards.forEach((card) => {
            // Target the <small> item containing brand text and <h4> product names
            const brandText = card.querySelector("small")?.textContent.toLowerCase() || "";
            const nameText = card.querySelector("h4")?.textContent.toLowerCase() || "";

            if (brandText.includes(searchTerm) || nameText.includes(searchTerm)) {
                card.style.display = ""; 
                visibleCount++;
            } else {
                card.style.display = "none"; 
            }
        });

        // Update total counter integer instantly
        counter.textContent = visibleCount;

        // Toggle "No Items Found" status message visibility state
        if (visibleCount === 0 && cards.length > 0) {
            fallbackNode.style.display = "block";
        } else {
            fallbackNode.style.display = "none";
        }
    });
});
</script>
