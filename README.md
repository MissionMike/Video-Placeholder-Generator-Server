# Video-Placeholder-Generator-Server
Server-side utility to generate a video placeholder image, especially useful for embedding in HTML email.

Accepts various GET parameters, and generates a static image with the imagemagick PHP extension.

**Note: It is recommended to set up your own copy of this [server-side installation](https://github.com/MissionMike/Video-Placeholder-Generator-Server) to host your own thumbnails. Perpetual hosting on the default api.missionmike.dev domain is NOT guaranteed!**

Customizable options include:
* Thumbnail Width
* Play Button Image (from URL)
* Play Button Width
* Play Button Opacity

Sample URL:

https://api.missionmike.dev/video-placeholder-generator/thumbnails/7NOSDKb0HlU.jpg?play_button_url=https%3A%2F%2Fapi.missionmike.dev%2Fvideo-placeholder-generator%2Fassets%2Fyoutube_play.png&width=600&play_button_width=80&play_button_opacity=80&save=false

**Here's what happens under the hood:**

1. If 7NOSDKb0H1U.jpg does not exist on the server, .htaccess will then route the request to ../generator.php
2. generator.php then parses the GET parameters, and uses imagemagick (PHP extension) to generate a graphic image.
3. If &save=false is not set, or if &save=true, then 7NOSDKb0H1U.jpg gets saved to the ./thumbnails/ directory -- if saved, subsequent calls do not consume any additional resources aside from serving the .jpg image!

More to come!

Try out the front-end utility here: https://www.missionmike.dev/video-placeholder-generator/

Check out the front-end utility here: https://github.com/MissionMike/Video-Placeholder-Generator-Client
