SGC SMARTTRACK — PRODUCTION ASSET PACK
======================================

Style
-----
Modern flat vector illustrations and lightweight SVG line icons.
Palette follows the SGC SmartTrack direction:
- Navy: #0F2D5B
- Brand blue: #2562EB
- Sky blue: #3B82F6
- Teal: #14B8A6
- Success: #22C55E
- Warning: #F59E0B
- Danger: #EF4444

Contents
--------
icons/           50+ individual 24x24 SVG UI icons
illustrations/   Dashboard hero, login artwork, empty states, and small UI scenes
patterns/        Reusable SVG backgrounds and patterns

Recommended placement in Laravel
--------------------------------
Copy the `icons`, `illustrations`, and `patterns` folders into:
public/assets/sgc-smarttrack/

Then reference assets with URLs such as:
- /assets/sgc-smarttrack/illustrations/dashboard-hero.svg
- /assets/sgc-smarttrack/illustrations/login-collaboration.svg
- /assets/sgc-smarttrack/illustrations/empty-records.svg
- /assets/sgc-smarttrack/icons/dashboard.svg
- /assets/sgc-smarttrack/patterns/hero-blue-waves.svg

Vue example
-----------
<img
  src="/assets/sgc-smarttrack/illustrations/dashboard-hero.svg"
  alt="School staff collaborating on school governance assessment"
  class="h-auto w-full max-w-2xl"
/>

CSS background example
----------------------
.sgc-hero-pattern {
  background-image: url('/assets/sgc-smarttrack/patterns/hero-blue-waves.svg');
  background-size: cover;
  background-position: center;
}

Icon example
------------
<img src="/assets/sgc-smarttrack/icons/assessment.svg" alt="" aria-hidden="true" width="24" height="24" />

Notes
-----
- All artwork is standalone SVG with explicit viewBox and no external dependencies.
- SVGs can scale sharply at any screen density and are usually smaller than raster artwork.
- Icons use stroke-based shapes and can be recolored by editing the SVG stroke values.
- Illustration SVGs use embedded vector shapes and are not dependent on remote images or fonts.
- These assets are separate from the existing app code. No project files were modified by this asset pack.
- Check your existing brand/logo and design standards before replacing official logos or government seals.
