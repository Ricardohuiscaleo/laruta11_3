export default function Logo({ size = "medium", showText = true }) {
  const sizes = {
    small: "w-8 h-8",
    medium: "w-12 h-12", 
    large: "w-16 h-16",
    xl: "w-24 h-24"
  };

  const textSizes = {
    small: "text-lg",
    medium: "text-2xl",
    large: "text-3xl", 
    xl: "text-4xl"
  };

  return (
    <div className="flex items-center space-x-3">
      <img 
        src="https://laruta11-images.s3.amazonaws.com/menu/1755571382_test.jpg"
        alt="La Ruta11 Logo"
        className={`${sizes[size]} rounded-full object-cover shadow-lg`}
      />
      {showText && (
        <div className={`${textSizes[size]} font-bold text-ruta-black`}>
          La Ruta<span className="text-ruta-red">11</span>
        </div>
      )}
    </div>
  );
}