/*
// Example Example based on https://schema.org/HowTo
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "How to Change a Car Tire",
  "description": "A step-by-step guide to changing a flat car tire safely and quickly.",
  "image": "https://example.com/images/change-tire.jpg",
  "prepTime": "PT15M",
  "performTime": "PT45M",
  "totalTime": "PT1H",
  "video": {
    "@type": "VideoObject",
    "name": "How to Change a Flat Tire",
    "description": "Watch this video to learn how to change a flat tire quickly and safely.",
    "thumbnailUrl": "https://example.com/thumbnails/tire-video.jpg",
    "uploadDate": "2024-06-01",
    "contentUrl": "https://example.com/videos/change-tire.mp4",
    "embedUrl": "https://www.youtube.com/embed/abc123xyz",
    "duration": "PT5M30S"
  },
  "estimatedCost": {
    "@type": "MonetaryAmount",
    "currency": "USD",
    "value": "10"
  },
  "supply": [
    {
      "@type": "HowToSupply",
      "name": "Spare tire"
    },
    {
      "@type": "HowToSupply",
      "name": "Car manual"
    }
  ],
  "tool": [
    {
      "@type": "HowToTool",
      "name": "Jack"
    },
    {
      "@type": "HowToTool",
      "name": "Lug wrench"
    }
  ],
  "performer": {
    "@type": "Person",
    "name": "John Doe"
  },
  "step": [
    {
      "@type": "HowToStep",
      "url": "https://example.com/change-tire#step1",
      "name": "Park the vehicle safely",
      "image": "https://example.com/images/step1.jpg",
      "text": "Pull over to a flat, safe location and turn on your hazard lights."
    },
    {
      "@type": "HowToStep",
      "url": "https://example.com/change-tire#step2",
      "name": "Loosen the lug nuts",
      "image": "https://example.com/images/step2.jpg",
      "text": "Use the wrench to loosen each lug nut about a quarter of a turn."
    },
    {
      "@type": "HowToStep",
      "name": "Raise the vehicle with a jack",
      "image": "https://example.com/images/step3.jpg",
      "text": "Place the jack under the vehicle's frame and lift it until the tire is off the ground."
    },
    {
      "@type": "HowToStep",
      "name": "Remove the flat tire and replace with spare",
      "image": "https://example.com/images/step4.jpg",
      "text": "Fully remove the loosened lug nuts, remove the flat tire, and install the spare."
    },
    {
      "@type": "HowToStep",
      "name": "Lower the car and tighten the lug nuts",
      "image": "https://example.com/images/step5.jpg",
      "text": "Lower the car and then fully tighten the lug nuts in a star pattern."
    }
  ]
}
*/

/*
  The pagination logic for the instruction steps lives inline in html.tpl,
  next to the markup it drives.

  It is kept there on purpose: on logged-out page views, caching and
  optimisation plugins inline and reorder the generated asset bundles, so
  addon JS could run before jQuery exists and fail silently - pagination then
  worked for logged-in admins only. Shipping it with the markup removes that
  dependency on the asset pipeline entirely.
*/
