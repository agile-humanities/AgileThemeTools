(function($) {
    $(document).ready(function() {
      // Also see https://stackoverflow.com/questions/32252337/how-to-style-text-tracks-in-html5-video-via-css
      // Also see https://stackoverflow.com/questions/72088924/styling-vtt-cues-cueid-selector-does-not-work

      let hasActive = false;
      let activeAudioObj = null;
      let activePlayObj = null;
      let activeControls = null;
      let activeProgress  = null;

      $('.ca-player-container').each(function(i){

        const containerObj = $(this);
        const playObj = $(this).find('svg')[0],
        audioObj = $(this).find(".ca-player")[0],
        controlsObj = playObj.querySelector(".controls"),
        progress = playObj.querySelector(".progress-bar"),
        precache = playObj.querySelector(".precache-bar"),
        timerDisplay = $(this).find('.ca-timer .current-time')[0],
        timerDuration = $(this).find('.ca-timer .duration')[0],
        progressTrack = playObj.querySelector(".progress-track");
        captionContainer = $(this).find(".ca-caption")[0],
        pt = playObj.createSVGPoint(),
        pc = 298.1371428256714; // 2 pi r

        let playerInitialized = false;

        getTextTrack(audioObj,captionContainer);

        /** Set to active object. Active object becomes the target of keyboard controls in instances where
         ** More than one player is on the same page. */

        if (i===0) {
          setActive();
        }

        /** Initializer. Iniatialized on first click interaction with the player controls (below). **/

        function initializePlayer() {
          audioObj.addEventListener('timeupdate', reportPosition);
          audioObj.addEventListener('timeupdate', updateTimer);
          precache.addEventListener('mousedown',positionAudio);
          progress.addEventListener('mousedown',positionAudio);
          progressTrack.addEventListener('mousedown', positionAudio)
          controlsObj.addEventListener('mousedown',setActive);
          progress.addEventListener('mousedown', setActive);
          playerInitialized = true;
        }

        /** Manages the state of the player. Initializes all listeners on first click. */

        controlsObj.addEventListener("click", function(e) {
          switch (playObj.getAttribute("class")) {
            case "not-started":
              // Initialize all event listeners
              initializePlayer();

              // Begin playback
              audioObj.play();

              // Update player state
              playObj.setAttribute("class", "playing");
              break;

            case "playing":
              playObj.setAttribute("class", "paused");
              audioObj.pause();
              break;

            case "paused":
              playObj.setAttribute("class", "playing");
              audioObj.play();
              break;

            case "ended":
              playObj.setAttribute("class", "not-started");
              audioObj.removeEventListener('timeupdate', reportPosition);
              audioObj.addEventListener('timeupdate', updateTimer);
              break;
            }
        });


        /** Updates graphics on  progress event. */

        audioObj.addEventListener('progress', function() {
                var length =  audioObj.buffered.length - 1;

                if (length > -1) {
                  setGraphValue(precache, audioObj.buffered.end(audioObj.buffered.length - 1));
                }
              });

        audioObj.addEventListener('loadedmetadata', setDuration);

        manageExternalTranscript(audioObj);

        /** Keyboard controls. Keyboard controls are applied to the active object in the case of multiple players */

        document.addEventListener('keydown', function(event) {
          if (!hasActive) return;

          const skipSeconds = 10;

          // Check for specific key presses
          if (event.code === 'Space') { // Spacebar
            event.preventDefault();
            if (activeAudioObj.paused) {
              activeAudioObj.play();
              activePlayObj.setAttribute("class", "playing");
            } else {
              activeAudioObj.pause();
              activePlayObj.setAttribute("class", "paused");
            }
          } else if (event.code === 'ArrowRight') {
            event.preventDefault();

            if ((activeAudioObj.currentTime + skipSeconds) < activeAudioObj.duration) {
                activeAudioObj.currentTime += skipSeconds;
                setGraphValue(activeProgress, activeAudioObj.currentTime);
                updateTimer();
              }
          } else if (event.code === 'ArrowLeft') {
              event.preventDefault();
              if ((activeAudioObj.currentTime - skipSeconds) > 0) {
                activeAudioObj.currentTime -= skipSeconds;
                reportPosition();
                updateTimer();

              }
          }
        });

        // Bug: in some instances the loadedmetadata event is never called.
        // This is a bit of duct tape to ensure that the audio length is presented.

        const updateDuration = setInterval(function(){
          timerDuration.textContent = timerDuration.textContent + ' .';
          if (audioObj && typeof audioObj.duration !== "undefined") {
            setDuration();
          }
        },1000)

        /** SUPPORTING FUNCTIONS */


        /*
         * Sets values for the active object. This allows events and scripts to target the player that a user has
         * most recently interacted with in the case where there are multiple players. Currently used to help
         * target keyboard controls.
         */

        function setActive() {
          activePlayObj = playObj;
          activeAudioObj = audioObj;
          activeControlObj = controlsObj;
          activeProgress = progress;
          hasActive = true;
        }

        function getTextTrack(video,captionContainer) {
          var span = document.querySelector('span');

          if (!video.textTracks) return;

          var track = video.textTracks[0];

          if (!track) return;

          track.mode = 'hidden';

          track.oncuechange = function(e) {
            var cue = this.activeCues[0];
            if (cue)  captionContainer.innerHTML = cue.getCueAsHTML().textContent;
          };
        }

        function cursorPoint(evt){
          pt.x = evt.clientX; pt.y = evt.clientY;
          return pt.matrixTransform(playObj.getScreenCTM().inverse());
        }

        function angle(ex, ey) {
          var dy = ey - 50; // 100;
          var dx = ex - 50; // 100;
          var theta = Math.atan2(dy, dx); // range (-PI, PI]
          theta *= 180 / Math.PI; // rads to degs, range (-180, 180]
          theta = theta + 90; // in our case we are animating from the top, so we offset by the rotation value;
          if (theta < 0) theta = 360 + theta; // range [0, 360)
          return theta;
        }

        function getSvgCenter(svgElement) {
          const bbox = svgElement.getBBox();
          const centerX = bbox.x + bbox.width / 2;
          const centerY = bbox.y + bbox.height / 2;
          return {
            x: centerX,
            y: centerY
          };
        }

        /* Basically a dupe of the angle function. Refactor */

        function calculateAngle(center, click) {
            const deltaX = click.x - center.x;
            const deltaY = click.y - center.y;

            // Math.atan2 returns the angle in radians
            const radians = Math.atan2(deltaY, deltaX);

            // Convert radians to degrees
            const degrees = radians * (180 / Math.PI);

            return degrees;
        }

        function degreesToPercentage(degrees) {
          // Ensure the input is a number
          if (typeof degrees !== 'number' || isNaN(degrees)) {
            throw new Error("Input must be a number.");
          }

          // Handle negative degrees by converting them to their positive equivalent within 0-360
          degrees = degrees % 360;
          if (degrees < 0) {
            degrees += 360;
          }

          const percentage = (degrees / 360);
          return percentage;
        }

        function setGraphValue(obj, val) {
          var val = pc - parseFloat(((val / audioObj.duration) * pc), 10);
          obj.style.strokeDashoffset = val;
          if (val === 0) {
            lunar.addClass(obj,"done");
            if (obj===progress) lunar.className(playObj, "ended");
          }
        }

        function setDuration() {
          const minutes = Math.floor(audioObj.duration / 60);
          const seconds = Math.floor(audioObj.duration % 60);
          timerDuration.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
          containerObj.css('opacity',1)
          clearInterval(updateDuration);
        }

        function reportPosition() {
          setGraphValue(progress, audioObj.currentTime);
        }

        function positionListener(event) {
          //   console.log("a",Math.sqrt((pc-loc.x)*(pc-loc.x) + (pc-loc.y)*    (pc-loc.y)));
          var loc = cursorPoint(event),
            deg = (angle(loc.x,loc.y) / 360),
            pct = pc * deg;
        }

        function updateTimer() {
          const minutes = Math.floor(audioObj.currentTime / 60);
          const seconds = Math.floor(audioObj.currentTime % 60);
          timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        function positionAudio(event) {
          var clickPoint = cursorPoint(event);
          var centrePoint = getSvgCenter(playObj);

          // Top of the circle is -90 degrees. Rotate to compensate.
          var degrees = calculateAngle(centrePoint,clickPoint) + 90;

          // Handle negative degrees after rotation.
          if (degrees < 0) degrees += 360;
          var timecode = Math.round(audioObj.duration * degreesToPercentage(degrees));
          audioObj.currentTime = timecode;
          setGraphValue(progress,audioObj.currentTime);
        }

        /**
         * Links audio player to parsed VTT files as per the Agile VTT media viewer.
         */

        function manageExternalTranscript(video) {

          if (typeof video === "undefined") {
            return;
          }

          $(video).find('track').each(function(i){
            const track = $(this)[0];

            if (track.hasAttribute('data-transcript-id') &&
              track.getAttribute('data-transcript-id') !== '' &&
              typeof document.getElementById(track.getAttribute('data-transcript-id') !== "undefined")) {
              const transcriptID = track.getAttribute('data-transcript-id');
              const transcript = document.getElementById(transcriptID);
              const transcriptSegments =  $(transcript).find('span');
              let transcriptCues = {};

             transcriptSegments.each(function(j) {
                const span = $(this);
                if (!span.attr('data-start-cue')) {
                  return;
                }
                const key = "segment-" + i + "-" + j;
                transcriptCues['ts-' + span.attr('data-start-cue')] = key
                span.attr('id',key);
                span.attr('title','Cue audio player to here')

                span.on('click',function() {
                  const cue = $(this).attr('data-start-cue') /1000;

                  if(typeof cue !== "undefined") {
                    video.currentTime = cue;
                    if (audioObj.paused === true) {

                      if (playerInitialized === false ) {
                        initializePlayer();
                      }

                      // audioObj.dispatchEvent(new Event('progress'));
                      // precache.dispatchEvent(new Event('mousedown'));
                      // progress.dispatchEvent(new Event('mousedown'));
                      playObj.setAttribute("class", "playing");
                      audioObj.play();
                    }
                  }

                })
              })

               track.addEventListener('cuechange',(e) =>{
                 const cues = e.target.track.activeCues;
                 console.log(cues);
                 
                 transcriptSegments.removeClass('cue-active');
                 if (typeof cues[0]  !== "undefined") {
                   const transcriptCueID = "ts-" + (cues[0].startTime * 1000);
                   const activeSegmentID = transcriptCues[transcriptCueID];


                   if ($('#' + activeSegmentID).length > 0)
                    $('#' + activeSegmentID).addClass('cue-active');
                 }

               });
            }
          });
        }
      });
    });
})(jQuery);

// https://github.com/toddmotto/lunar/
(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define(factory);
  } else if (typeof exports === 'object') {
    module.exports = factory;
  } else {
    root.lunar = factory();
  }
})(this, function () {

  'use strict';

  var lunar = {};

  lunar.hasClass = function (elem, name) {
    return new RegExp('(\\s|^)' + name + '(\\s|$)').test(elem.getAttribute('class'));
  };

  lunar.addClass = function (elem, name) {
    !lunar.hasClass(elem, name) && elem.setAttribute('class', (!!elem.getAttribute('class') ? elem.getAttribute('class') + ' ' : '') + name);
  };

  lunar.removeClass = function (elem, name) {
    var remove = elem.getAttribute('class').replace(new RegExp('(\\s|^)' + name + '(\\s|$)', 'g'), '$2');
    lunar.hasClass(elem, name) && elem.setAttribute('class', remove);
  };

  lunar.toggleClass = function (elem, name) {
    lunar[lunar.hasClass(elem, name) ? 'removeClass' : 'addClass'](elem, name);
  };

  lunar.className = function (elem, name) {
    elem.setAttribute("class", name);
    console.log("className", elem);
  }

  return lunar;

});



