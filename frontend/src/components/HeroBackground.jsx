import heroSky from '../assets/hero-sky.jpg'
import courtScene from '../assets/court-scene.webp'

// The picture behind the whole hero. It is built from stacked layers (bottom layer first).
// They are only decoration, so the wrapper is aria-hidden.
//
// The artwork was prepared as separate image files so that it looks like ONE scene:
//   hero-sky.jpg     : a stormy, layered cloud sky with soft light behind the dome
//   court-scene.webp : the Supreme Court (its sky is transparent, so the clouds show through)
//                      together with the dark wet-stone floor and the court's rippled reflection.
//                      The map of Bangladesh in the pointed arch sits on the building base.
// Lady Justice (and her own reflection) is a separate file, see HeroArtwork.jsx.

// Where the court scene sits (its height follows the picture's shape, so it is never stretched).
//   Phone/tablet: glued to the bottom of the hero, wider than the screen (dome in the middle).
//   Desktop (lg:): starts near the top, as wide as the hero (but never taller than the screen
//                  allows - "175vh"), and placed so that the dome is at 57% of the page width.
const courtBox =
  'absolute bottom-0 left-[-28%] aspect-[1720/1012] w-[170%] sm:left-[-5.5%] sm:w-[125%] ' +
  'lg:bottom-auto lg:top-[11%] lg:left-[calc(57%_-_min(100%,175vh)_/_2)] lg:w-[min(100%,175vh)]'

// The left and right ends of the scene fade into the dark page (no hard edge).
const fadeSides =
  '[mask-image:linear-gradient(to_right,transparent,black_5%,black_95%,transparent)]'

function HeroBackground() {
  return (
    <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden bg-deep">
      {/* 1. The cloudy sky. "object-cover" fills the hero; the bright gap in the clouds ends up
             behind the dome. */}
      <img
        src={heroSky}
        alt=""
        draggable="false"
        className="absolute inset-0 size-full object-cover object-[57%_30%]"
      />

      {/* 2. The Supreme Court + wet floor + reflection (one picture) */}
      <div className={courtBox + ' ' + fadeSides}>
        <img src={courtScene} alt="" draggable="false" className="block size-full" />
      </div>

      {/* 3. Everything at the very bottom melts into the page colour */}
      <div className="absolute inset-0 bg-linear-to-b from-transparent from-86% to-deep/85" />

      {/* 4. Desktop: shade on the left, so the ivory text is easy to read */}
      <div className="absolute inset-0 hidden bg-linear-to-r from-deep/80 from-0% via-deep/45 via-38% to-transparent to-62% lg:block" />

      {/* 5. Phone/tablet: the text sits on the sky, so darken the upper part a little */}
      <div className="absolute inset-0 bg-linear-to-b from-deep/55 via-deep/25 via-45% to-transparent lg:hidden" />

      {/* 6. Dark at the top (under the navbar) */}
      <div className="absolute inset-x-0 top-0 h-32 bg-linear-to-b from-deep/80 to-transparent" />

      {/* 7. Soft dark corners (vignette) */}
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_58%,rgba(34,37,43,0.55))]" />
    </div>
  )
}

export default HeroBackground
