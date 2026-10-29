import ladyJustice from '../assets/lady-justice-lit.webp'

// Lady Justice, on the RIGHT side of the hero.
//   Phone / tablet: she sits below the text, in normal page flow, standing on the floor.
//   Desktop (lg:)  : she is placed absolutely on the right, so she can never cover the text.
//
// The picture is a transparent file. It already contains:
//   - the statue in silver metal with a warm gold rim light (made from the project's statue photo)
//   - her rippled, golden reflection on the wet floor, in the lowest part of the picture
// So the feet are at about 80% of the picture's height and the reflection fills the rest.
function HeroArtwork() {
  return (
    <div className="relative z-10 ml-auto mr-[4%] mt-2 h-[370px] w-fit sm:mr-[9%] sm:h-[500px] lg:absolute lg:right-[7%] lg:top-[14%] lg:m-0 lg:h-[95%]">
      <img
        src={ladyJustice}
        alt="Lady Justice holding a sword and the scales of justice"
        draggable="false"
        className="block h-full w-auto select-none"
      />
    </div>
  )
}

export default HeroArtwork
